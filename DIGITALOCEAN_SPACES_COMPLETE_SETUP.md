# Complete DigitalOcean Spaces Setup Guide
## Step-by-Step Instructions for Server & Client

---

## 📋 Prerequisites

- DigitalOcean account
- Access to your App Platform app settings
- Terminal/command line access
- Your app repository

---

## Part 1: DigitalOcean Spaces Setup

### Step 1: Create a DigitalOcean Space

1. **Go to DigitalOcean Control Panel**
   - Visit: https://cloud.digitalocean.com/spaces
   - Or: Control Panel → **Spaces** (left sidebar)

2. **Click "Create a Space"**

3. **Configure your Space:**
   - **Name**: `` (must be globally unique, lowercase, no spaces)
   - **Region**: Choose the **same region** as your App Platform app
     - Common regions: `nyc3` (New York), `sgp1` (Singapore), `ams3` (Amsterdam), `sfo3` (San Francisco)
     - **How to find your app region**: App Platform → Your App → Settings → Region
   - **CDN**: ✅ **Enable CDN** (recommended - faster image loading worldwide)
   - **File Listing**: ❌ **Disable** (for security - prevents public directory browsing)

4. **Click "Create a Space"**

5. **Wait for creation** (usually 1-2 minutes)

6. **Note your Space details:**
   - Space name: `ajcreativestudio-files` (or whatever you named it)
   - Region: `nyc3` (or your chosen region)
   - CDN endpoint: Will be shown (e.g., `ajcreativestudio-files.nyc3.cdn.digitaloceanspaces.com`)

---

### Step 2: Generate Access Keys

1. **Go to Spaces Keys**
   - Visit: https://cloud.digitalocean.com/account/api/spaces
   - Or: Control Panel → **API** → **Spaces Keys** (left sidebar)

2. **Click "Generate New Key"**

3. **Configure the key:**
   - **Name**: `ajcreativestudio-spaces-key` (or any descriptive name)

4. **Click "Generate New Key"**

5. **⚠️ IMPORTANT: Copy both keys immediately**
   - **Access Key**: Starts with something like `DO1234567890ABCDEF`
   - **Secret Key**: Long random string
   - **You will NOT be able to see the Secret Key again!** Save it securely.

6. **Save the keys** in a secure place (password manager, secure note, etc.)

---

## Part 2: Server-Side Setup

### Step 3: Verify AWS SDK Package

The Laravel S3 driver requires the AWS SDK package. Let's verify it's installed.

1. **Check if AWS SDK is installed:**
   - The package `league/flysystem-aws-s3-v3` is already included in your `composer.lock`
   - This means it's already installed! ✅
   - **No action needed** - you can skip to Step 4

   **If for some reason it's not installed** (unlikely), run:
   ```bash
   cd server
   composer require league/flysystem-aws-s3-v3 "^3.0"
   git add composer.json composer.lock
   git commit -m "Add AWS S3 SDK for DigitalOcean Spaces support"
   ```

---

### Step 4: Configure Environment Variables in App Platform

1. **Go to your App Platform app**
   - Visit: https://cloud.digitalocean.com/apps
   - Click on your app (e.g., `ajcreativestudio-server`)

2. **Navigate to Settings**
   - Click **Settings** tab (top menu)

3. **Go to Environment Variables**
   - Scroll down to **App-Level Environment Variables** section
   - Click **Edit** (or **Add Variable** if empty)

4. **Add the following environment variables:**

   Click **Add Variable** for each one:

   | Variable Name | Value | Example |
   |--------------|-------|---------|
   | `PRODUCT_STORAGE_DISK` | `s3` | `s3` |
   | `AWS_ACCESS_KEY_ID` | Your Access Key from Step 2 | `DO1234567890ABCDEF` |
   | `AWS_SECRET_ACCESS_KEY` | Your Secret Key from Step 2 | `abc123def456...` |
   | `AWS_DEFAULT_REGION` | Your Space region | `nyc3` |
   | `AWS_BUCKET` | Your Space name | `ajcreativestudio-files` |
   | `AWS_ENDPOINT` | `https://{region}.digitaloceanspaces.com` | `https://nyc3.digitaloceanspaces.com` |
   | `AWS_USE_PATH_STYLE_ENDPOINT` | `false` | `false` |

   **Detailed instructions for each:**

   **PRODUCT_STORAGE_DISK:**
   - Variable: `PRODUCT_STORAGE_DISK`
   - Value: `s3`
   - Type: Plain text

   **AWS_ACCESS_KEY_ID:**
   - Variable: `AWS_ACCESS_KEY_ID`
   - Value: The Access Key you copied in Step 2
   - Type: Plain text

   **AWS_SECRET_ACCESS_KEY:**
   - Variable: `AWS_SECRET_ACCESS_KEY`
   - Value: The Secret Key you copied in Step 2
   - Type: **Encrypted** (recommended for security)

   **AWS_DEFAULT_REGION:**
   - Variable: `AWS_DEFAULT_REGION`
   - Value: Your Space region (e.g., `nyc3`, `sgp1`, `ams3`)
   - Type: Plain text

   **AWS_BUCKET:**
   - Variable: `AWS_BUCKET`
   - Value: Your Space name (e.g., `ajcreativestudio-files`)
   - Type: Plain text

   **AWS_ENDPOINT:**
   - Variable: `AWS_ENDPOINT`
   - Value: `https://{region}.digitaloceanspaces.com` (replace `{region}` with your region)
   - Example: `https://nyc3.digitaloceanspaces.com`
   - Type: Plain text

   **AWS_USE_PATH_STYLE_ENDPOINT:**
   - Variable: `AWS_USE_PATH_STYLE_ENDPOINT`
   - Value: `false`
   - Type: Plain text

5. **Save the environment variables**
   - Click **Save** or **Save Changes**

6. **Verify all variables are added:**
   - You should see all 7 variables listed
   - Make sure there are no typos in variable names (they're case-sensitive)

---

### Step 5: Verify Server Configuration

The server-side code is already configured! Here's what's already set up:

✅ **Storage routes** (`server/routes/api.php` and `server/routes/web.php`):
   - Already handle S3/Spaces
   - Automatically redirect to Spaces CDN URLs when using S3

✅ **Product controller** (`server/app/Http/Controllers/ProductController.php`):
   - Already uses configurable disk (`config('products.storage_disk')`)
   - Works with both local and S3 storage

✅ **Filesystem config** (`server/config/filesystems.php`):
   - S3 disk already configured
   - Reads from environment variables

✅ **Product config** (`server/config/products.php`):
   - Storage disk configuration already set up

**No code changes needed!** The server is ready.

---

## Part 3: Client-Side Setup

### Step 6: Verify Client Configuration

The client-side code is already configured! Here's what's already set up:

✅ **Image utilities** (`client/src/utils/productImageUtils.js`):
   - Already handles full URLs (if the API returns Spaces CDN URLs, they'll be used directly)
   - Falls back to building API URLs for local storage

✅ **Product components**:
   - Already use `productImageUtils.js` for image URLs
   - No changes needed

**No code changes needed!** The client is ready.

---

## Part 4: Deploy and Test

### Step 7: Deploy Your App

1. **Commit any changes** (if you added the AWS SDK package):
   ```bash
   git add .
   git commit -m "Configure DigitalOcean Spaces for persistent file storage"
   git push
   ```

2. **Trigger a deployment** (if auto-deploy is enabled, it will deploy automatically):
   - Go to App Platform → Your App
   - If needed, click **Actions** → **Force Rebuild**

3. **Wait for deployment** to complete (usually 2-5 minutes)

---

### Step 8: Test the Setup

1. **Upload a test product:**
   - Go to your admin panel
   - Create a new product with:
     - Thumbnail image
     - Feature images
     - Product file (Excel/PDF)

2. **Verify files in Spaces:**
   - Go to DigitalOcean Control Panel → Spaces
   - Click on your Space (`ajcreativestudio-files`)
   - You should see folders:
     - `products/thumbnails/` - Contains thumbnail images
     - `products/features/` - Contains feature images
     - `products/` - Contains product files

3. **Verify images load on your site:**
   - Go to your public site
   - Check product listings - images should load
   - Check product detail pages - images should load
   - Open browser DevTools (F12) → Network tab
   - Images should load from Spaces CDN (URLs like `https://ajcreativestudio-files.nyc3.cdn.digitaloceanspaces.com/...`)

4. **Test persistence:**
   - Force a redeploy: App Platform → Your App → Actions → Force Rebuild
   - Wait for redeploy to complete
   - Check your site again - images should still load! ✅

---

## Part 5: Migrate Existing Images (Optional)

If you have existing products with images in the database but missing files:

### Option A: Re-upload through Admin (Recommended)

1. Go to admin panel → Products
2. Edit each product
3. Re-upload the thumbnail and feature images
4. Save

### Option B: Use Sync Script (If you have local copies)

1. **Make sure you have local image files** in `server/storage/app/public/`

2. **Run the sync script:**
   ```bash
   cd server
   php upload-images.php
   ```

   This will upload local images to Spaces via the admin API.

---

## ✅ Verification Checklist

After setup, verify everything works:

- [ ] Space created in DigitalOcean
- [ ] Access keys generated and saved
- [ ] AWS SDK package installed (if needed)
- [ ] All 7 environment variables set in App Platform
- [ ] App deployed successfully
- [ ] Test product uploaded with images
- [ ] Files visible in Spaces dashboard
- [ ] Images load on public site
- [ ] Images load from Spaces CDN (check Network tab)
- [ ] Images persist after redeploy

---

## 🔧 Troubleshooting

### Images still not showing?

1. **Check environment variables:**
   - Go to App Platform → Settings → Environment Variables
   - Verify all 7 variables are set correctly
   - Check for typos (especially `PRODUCT_STORAGE_DISK=s3`)

2. **Check Space name and region:**
   - `AWS_BUCKET` must match your Space name exactly
   - `AWS_DEFAULT_REGION` must match your Space region exactly
   - `AWS_ENDPOINT` must use the correct region

3. **Check access keys:**
   - Verify `AWS_ACCESS_KEY_ID` and `AWS_SECRET_ACCESS_KEY` are correct
   - Make sure Secret Key is the full string (no truncation)

4. **Check app logs:**
   - App Platform → Your App → Runtime Logs
   - Look for storage-related errors
   - Common errors:
     - `Access Denied` → Wrong access keys
     - `Bucket not found` → Wrong bucket name or region
     - `Invalid endpoint` → Wrong endpoint URL

5. **Test upload:**
   - Try uploading a new product image
   - Check if it appears in Spaces dashboard
   - If it doesn't appear, check logs for errors

### Getting 403 Forbidden errors?

- **Check access keys** are correct
- **Verify Space name** matches `AWS_BUCKET`
- **Check Space region** matches `AWS_DEFAULT_REGION`
- **Ensure Space is public** (for CDN to work)

### Files uploading but not visible?

- **Check CDN is enabled** on your Space
- **Clear browser cache** (Ctrl+Shift+R or Cmd+Shift+R)
- **Check Network tab** in DevTools - are requests going to Spaces CDN?
- **Verify `AWS_URL`** (optional - usually auto-generated, but you can set it manually to your CDN endpoint)

### Images load but slowly?

- **Enable CDN** on your Space (if not already enabled)
- **Check CDN endpoint** is being used (URLs should have `.cdn.` in them)
- **Verify region** is close to your users

---

## 📊 How It Works

### Before (Local Storage):
```
User uploads image → Stored in storage/app/public → Lost on redeploy → 404 errors
```

### After (Spaces):
```
User uploads image → Stored in DigitalOcean Spaces → Persists forever → Images always load
```

### URL Flow:

1. **Upload:**
   - User uploads image via admin panel
   - Laravel stores it in Spaces using S3 driver
   - Database stores path: `products/thumbnails/1234567890_image.png`

2. **Display:**
   - Frontend requests image via `/api/storage/products/thumbnails/1234567890_image.png`
   - Laravel checks `PRODUCT_STORAGE_DISK=s3`
   - Laravel redirects to Spaces CDN URL: `https://ajcreativestudio-files.nyc3.cdn.digitaloceanspaces.com/products/thumbnails/1234567890_image.png`
   - Browser loads image from CDN (fast!)

---

## 💰 Cost

DigitalOcean Spaces pricing:
- **Storage**: $5/month for 250 GB
- **Bandwidth**: First 1 TB/month free, then $0.01/GB
- **CDN**: Included (free)

**Typical e-commerce site**: $5-10/month total

---

## 🎉 Success!

Once everything is set up:
- ✅ Images persist across redeploys
- ✅ Images load from CDN (fast worldwide)
- ✅ No more 404 errors
- ✅ Scalable storage (unlimited)

Your images will now **never disappear** again!

---

## 📚 Additional Resources

- [DigitalOcean Spaces Documentation](https://docs.digitalocean.com/products/spaces/)
- [Laravel Filesystem Documentation](https://laravel.com/docs/filesystem)
- [S3-Compatible API Guide](https://docs.digitalocean.com/products/spaces/how-to/upload-files/)

---

## Need Help?

If you encounter issues:
1. Check the Troubleshooting section above
2. Review App Platform logs
3. Verify all environment variables are set correctly
4. Test with a new product upload to isolate the issue

