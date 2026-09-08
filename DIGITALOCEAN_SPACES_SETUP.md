# DigitalOcean Spaces Setup Guide

## Why Images Disappear After Hours/Days

**Root Cause**: DigitalOcean App Platform uses an **ephemeral filesystem**. This means:
- Files uploaded to `storage/app/public` are stored on the container's local filesystem
- When the app redeploys, restarts, or the container is recreated (which happens automatically), **all files are lost**
- The database still has the paths (like `products/thumbnails/1769157854_thumbnail_1.png`), but the actual files are gone
- This is why images work right after upload but disappear after a few hours/days

**Solutions**: 
1. **DigitalOcean Spaces** (S3-compatible object storage) - Recommended for CDN and scalability
2. **DigitalOcean Volume** (persistent block storage) - Alternative if you want files on the server (see `DIGITALOCEAN_VOLUME_SETUP.md`)

---

## Step 1: Create a DigitalOcean Space

1. Go to [DigitalOcean Control Panel](https://cloud.digitalocean.com/spaces)
2. Click **"Create a Space"**
3. Configure:
   - **Name**: `ajcreativestudio-files` (or your preferred name)
   - **Region**: Choose the same region as your App Platform app (e.g., `nyc3`)
   - **CDN**: Enable CDN (recommended for faster image loading)
   - **File Listing**: Disable (for security)
4. Click **"Create a Space"**

---

## Step 2: Generate Access Keys

1. Go to [API → Spaces Keys](https://cloud.digitalocean.com/account/api/spaces)
2. Click **"Generate New Key"**
3. Give it a name (e.g., `ajcreativestudio-spaces-key`)
4. **Copy both the Access Key and Secret Key** (you won't see the secret again!)

---

## Step 3: Configure Environment Variables

In your DigitalOcean App Platform app settings:

1. Go to **Settings → App-Level Environment Variables**
2. Add these variables:

```
PRODUCT_STORAGE_DISK=s3
AWS_ACCESS_KEY_ID=your_access_key_here
AWS_SECRET_ACCESS_KEY=your_secret_key_here
AWS_DEFAULT_REGION=nyc3
AWS_BUCKET=ajcreativestudio-files
AWS_ENDPOINT=https://nyc3.digitaloceanspaces.com
AWS_USE_PATH_STYLE_ENDPOINT=false
```

**Important Notes**:
- Replace `your_access_key_here` and `your_secret_key_here` with the keys from Step 2
- Replace `nyc3` with your Space's region (e.g., `sgp1`, `ams3`, `sfo3`)
- Replace `ajcreativestudio-files` with your Space name
- The endpoint format is: `https://{region}.digitaloceanspaces.com`

---

## Step 4: Install AWS SDK (if not already installed)

The Laravel S3 driver requires the AWS SDK. Check if it's installed:

```bash
cd server
composer show | grep aws
```

If not installed, add it:

```bash
composer require league/flysystem-aws-s3-v3 "^3.0"
```

---

## Step 5: Deploy and Test

1. **Commit and push** your code (the storage routes are already updated to support S3)
2. **Redeploy** your app on DigitalOcean
3. **Upload a new product** with images
4. **Check the Space** in DigitalOcean Control Panel - you should see files in `products/thumbnails/` and `products/features/`
5. **Verify images load** on your site

---

## Step 6: Migrate Existing Images (Optional)

If you have existing products with images in the database but missing files:

1. **Re-upload images** through the admin panel for each product
2. OR use the sync script (if you have local copies):
   ```bash
   cd server
   php upload-images.php
   ```

---

## How It Works Now

- **Before**: Files stored in `storage/app/public` → Lost on redeploy → 404 errors
- **After**: Files stored in DigitalOcean Spaces → Persist forever → Images always load

The code automatically:
- Uses `public` disk (local) when `PRODUCT_STORAGE_DISK` is not set
- Uses `s3` disk (Spaces) when `PRODUCT_STORAGE_DISK=s3` is set
- Redirects to Spaces CDN URLs for images (faster than streaming through Laravel)
- Handles both local and S3 storage transparently

---

## Troubleshooting

### Images still not showing after setup?

1. **Check environment variables** are set correctly in App Platform
2. **Verify Space name and region** match your configuration
3. **Check Space permissions** - ensure the access key has read/write access
4. **Test upload** - try uploading a new product image and check if it appears in the Space
5. **Check logs** - look for storage-related errors in App Platform logs

### Getting 403 Forbidden errors?

- Check that your AWS access keys are correct
- Verify the Space name matches `AWS_BUCKET`
- Ensure the Space region matches `AWS_DEFAULT_REGION`

### Files uploading but not visible?

- Check if CDN is enabled on your Space
- Verify `AWS_URL` is set correctly (usually auto-generated, but you can set it manually)
- Clear browser cache

---

## Cost

DigitalOcean Spaces pricing:
- **Storage**: $5/month for 250 GB
- **Bandwidth**: First 1 TB/month free, then $0.01/GB
- **CDN**: Included (free)

For a typical e-commerce site, this is usually **$5-10/month** total.

---

## Alternative: Keep Using Local Storage (Not Recommended)

If you want to keep using local storage (not recommended for production):

1. **Don't set** `PRODUCT_STORAGE_DISK` (or set it to `public`)
2. **Accept that images will disappear** on redeploy
3. **Re-upload images** after each deployment

This is **not recommended** for production because:
- Images disappear on every redeploy
- Poor user experience
- Time-consuming to re-upload images constantly

