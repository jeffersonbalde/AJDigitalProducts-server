<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class ProductFileUploadController extends Controller
{
    private const MAX_FILE_SIZE_BYTES = 20 * 1024 * 1024; // 20MB
    private const MAX_CHUNK_SIZE_KB = 2048; // 2MB per chunk (keep client below this)

    public function init(Request $request, Product $product): Response
    {
        $validator = Validator::make($request->all(), [
            'file_name' => 'required|string|max:255',
            'file_size' => 'required|integer|min:1|max:' . self::MAX_FILE_SIZE_BYTES,
            'total_chunks' => 'required|integer|min:1|max:5000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        $extension = strtolower(pathinfo($data['file_name'], PATHINFO_EXTENSION));
        if (! in_array($extension, ['xlsx', 'xls', 'pdf'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid file type. Only .xlsx, .xls, or .pdf files are allowed.',
            ], 422);
        }

        $uploadId = (string) Str::uuid();
        $tempDir = $this->tempDir($uploadId);

        Storage::disk('local')->makeDirectory($tempDir);
        Storage::disk('local')->put($tempDir . '/meta.json', json_encode([
            'product_id' => $product->id,
            'file_name' => $data['file_name'],
            'file_size' => (int) $data['file_size'],
            'total_chunks' => (int) $data['total_chunks'],
            'created_at' => now()->toIso8601String(),
        ], JSON_THROW_ON_ERROR));

        return response()->json([
            'success' => true,
            'upload_id' => $uploadId,
        ]);
    }

    public function chunk(Request $request, Product $product): Response
    {
        $validator = Validator::make($request->all(), [
            'upload_id' => 'required|string',
            'chunk_index' => 'required|integer|min:0',
            'total_chunks' => 'required|integer|min:1|max:5000',
            'chunk' => 'required|file|max:' . self::MAX_CHUNK_SIZE_KB,
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        $uploadId = (string) $data['upload_id'];
        $tempDir = $this->tempDir($uploadId);

        if (! Storage::disk('local')->exists($tempDir . '/meta.json')) {
            return response()->json([
                'success' => false,
                'message' => 'Upload session not found (missing meta). Please restart the upload.',
            ], 404);
        }

        $meta = json_decode(Storage::disk('local')->get($tempDir . '/meta.json'), true);
        if (! is_array($meta) || ($meta['product_id'] ?? null) !== $product->id) {
            return response()->json([
                'success' => false,
                'message' => 'Upload session does not match this product.',
            ], 422);
        }

        $chunkIndex = (int) $data['chunk_index'];
        $totalChunks = (int) $data['total_chunks'];

        if (($meta['total_chunks'] ?? null) !== $totalChunks) {
            return response()->json([
                'success' => false,
                'message' => 'Total chunks mismatch. Please restart the upload.',
            ], 422);
        }

        $chunkFile = $request->file('chunk');
        if (! $chunkFile) {
            return response()->json([
                'success' => false,
                'message' => 'Missing chunk file.',
            ], 422);
        }

        $chunkPath = $chunkFile->storeAs($tempDir, sprintf('%06d.part', $chunkIndex), 'local');
        if (! $chunkPath || ! Storage::disk('local')->exists($chunkPath)) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to store chunk on server.',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'chunk_index' => $chunkIndex,
        ]);
    }

    public function complete(Request $request, Product $product): Response
    {
        $validator = Validator::make($request->all(), [
            'upload_id' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $uploadId = (string) $validator->validated()['upload_id'];
        $tempDir = $this->tempDir($uploadId);

        if (! Storage::disk('local')->exists($tempDir . '/meta.json')) {
            return response()->json([
                'success' => false,
                'message' => 'Upload session not found (missing meta). Please restart the upload.',
            ], 404);
        }

        $meta = json_decode(Storage::disk('local')->get($tempDir . '/meta.json'), true);
        if (! is_array($meta) || ($meta['product_id'] ?? null) !== $product->id) {
            return response()->json([
                'success' => false,
                'message' => 'Upload session does not match this product.',
            ], 422);
        }

        $fileName = (string) ($meta['file_name'] ?? '');
        $expectedSize = (int) ($meta['file_size'] ?? 0);
        $totalChunks = (int) ($meta['total_chunks'] ?? 0);

        if ($fileName === '' || $expectedSize <= 0 || $totalChunks <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid upload metadata. Please restart the upload.',
            ], 422);
        }

        // Ensure all chunks exist
        for ($i = 0; $i < $totalChunks; $i++) {
            $partPath = $tempDir . '/' . sprintf('%06d.part', $i);
            if (! Storage::disk('local')->exists($partPath)) {
                return response()->json([
                    'success' => false,
                    'message' => "Missing chunk {$i}. Please retry upload.",
                ], 422);
            }
        }

        // Assemble (use the configured local disk root; in this app 'local' points to storage/app/private)
        $assembledLocalPath = Storage::disk('local')->path($tempDir . '/assembled.tmp');
        $out = fopen($assembledLocalPath, 'wb');
        if ($out === false) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to open output file for assembly.',
            ], 500);
        }

        try {
            for ($i = 0; $i < $totalChunks; $i++) {
                $partStoragePath = $tempDir . '/' . sprintf('%06d.part', $i);
                $partLocalPath = Storage::disk('local')->path($partStoragePath);
                $in = fopen($partLocalPath, 'rb');
                if ($in === false) {
                    throw new \RuntimeException("Failed to open chunk {$i} for reading.");
                }
                stream_copy_to_stream($in, $out);
                fclose($in);
            }
        } finally {
            fclose($out);
        }

        $actualSize = filesize($assembledLocalPath);
        if ($actualSize === false || (int) $actualSize !== $expectedSize) {
            @unlink($assembledLocalPath);
            return response()->json([
                'success' => false,
                'message' => 'Assembled file size mismatch. Please retry upload.',
            ], 422);
        }

        $disk = config('products.storage_disk', 'public');

        // Delete old file if exists
        if ($product->file_path && Storage::disk($disk)->exists($product->file_path)) {
            Storage::disk($disk)->delete($product->file_path);
        }

        $storedName = time() . '_' . basename($fileName);
        $storedPath = 'products/' . $storedName;
        $stream = fopen($assembledLocalPath, 'rb');
        if ($stream === false) {
            @unlink($assembledLocalPath);
            return response()->json([
                'success' => false,
                'message' => 'Failed to read assembled file for storage.',
            ], 500);
        }

        Storage::disk($disk)->put($storedPath, $stream);
        fclose($stream);
        @unlink($assembledLocalPath);

        if (! Storage::disk($disk)->exists($storedPath)) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to store assembled file on server storage.',
            ], 500);
        }

        $product->update([
            'file_path' => $storedPath,
            'file_name' => basename($fileName),
            'file_size' => $expectedSize,
        ]);

        // Cleanup temp directory
        Storage::disk('local')->deleteDirectory($tempDir);

        $product->load(['addedByAdmin', 'addedByPersonnel']);
        $productData = $product->toArray();
        $productData['added_by_name'] = $product->added_by_name;

        return response()->json([
            'success' => true,
            'message' => 'Product file uploaded successfully',
            'product' => $productData,
        ]);
    }

    private function tempDir(string $uploadId): string
    {
        return 'temp/product_uploads/' . $uploadId;
    }
}


