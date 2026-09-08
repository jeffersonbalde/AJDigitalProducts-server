# DigitalOcean Volume Setup (Alternative to Spaces)

## What is a Volume?

A **DigitalOcean Volume** is a persistent block storage device that can be mounted to your App Platform app. Unlike the ephemeral filesystem, files stored on a volume **persist across redeploys**.

**Pros:**
- Files persist on the server (no external service needed)
- Works like a regular filesystem
- No code changes needed (just mount it)

**Cons:**
- Still requires some configuration (app spec YAML)
- Volumes are region-specific
- Slightly more complex than Spaces

---

## Step 1: Create a Volume

1. Go to [DigitalOcean Control Panel → Volumes](https://cloud.digitalocean.com/volumes)
2. Click **"Create Volume"**
3. Configure:
   - **Name**: `ajcreativestudio-storage` (or your preferred name)
   - **Region**: **Must match your App Platform app region** (e.g., `nyc3`)
   - **Size**: Start with 10 GB (you can resize later)
   - **Filesystem**: `ext4` (default)
4. Click **"Create Volume"**
5. **Copy the Volume ID** (you'll need it in Step 2)

---

## Step 2: Mount Volume in App Spec

1. In your DigitalOcean App Platform, go to **Settings → App Spec**
2. Edit the YAML file to add the volume mount

**Find your app spec** (it should look something like this):

```yaml
name: ajcreativestudio-server
services:
- name: api
  source_dir: /server
  github:
    repo: your-repo
    branch: main
  run_command: php artisan serve --host=0.0.0.0 --port=8080
  http_port: 8080
  instance_count: 1
  instance_size_slug: basic-xxs
  envs:
  - key: APP_ENV
    value: production
```

**Add the volume mount** to your service:

```yaml
name: ajcreativestudio-server
services:
- name: api
  source_dir: /server
  github:
    repo: your-repo
    branch: main
  run_command: php artisan serve --host=0.0.0.0 --port=8080
  http_port: 8080
  instance_count: 1
  instance_size_slug: basic-xxs
  # ADD THIS SECTION:
  mounts:
  - mount_path: /workspace/storage/app/public
    name: ajcreativestudio-storage
  envs:
  - key: APP_ENV
    value: production
```

**Important Notes:**
- `mount_path`: This is where your Laravel `storage/app/public` directory will be mounted
- `name`: This is the **Volume ID** you copied in Step 1 (or the volume name)

3. **Save** the app spec
4. **Deploy** your app (the volume will be mounted automatically)

---

## Step 3: Verify It Works

1. **Upload a product image** through your admin panel
2. **Check the volume** in DigitalOcean Control Panel → Volumes → Your Volume → Files
3. **Redeploy your app** (Settings → Force Rebuild)
4. **Check if the image still loads** - it should! 🎉

---

## How It Works

- **Before**: Files stored in `storage/app/public` → Lost on redeploy
- **After**: Files stored in `storage/app/public` → **Mounted to persistent volume** → Persist forever

The volume is mounted **directly to your Laravel storage directory**, so:
- No code changes needed
- Works exactly like local storage
- Files persist across redeploys

---

## Troubleshooting

### Volume not mounting?

1. **Check the mount path** - it must match your Laravel storage path exactly
2. **Verify volume region** matches your app region
3. **Check app spec syntax** - YAML is sensitive to indentation
4. **Look at app logs** for mount errors

### Files still disappearing?

1. **Verify the mount path** is correct in your app spec
2. **Check if volume is attached** in DigitalOcean Control Panel
3. **Test by creating a file** directly on the volume and checking if it persists

### Volume full?

1. Go to Volumes → Your Volume → **Resize**
2. Increase the size (you can only increase, not decrease)
3. The resize happens automatically

---

## Cost

DigitalOcean Volumes pricing:
- **$0.10/GB/month** (e.g., 10 GB = $1/month)
- **No bandwidth charges** (unlike Spaces)

For a typical e-commerce site, this is usually **$1-5/month** total.

---

## Comparison: Volume vs Spaces

| Feature | Volume | Spaces |
|---------|--------|--------|
| **Setup Complexity** | Medium (app spec YAML) | Easy (env vars) |
| **Persistence** | ✅ Yes | ✅ Yes |
| **CDN** | ❌ No | ✅ Yes (faster) |
| **Scalability** | Limited (single volume) | Unlimited |
| **Cost** | $0.10/GB/month | $5/month + bandwidth |
| **Code Changes** | None needed | None needed (already done) |

**Recommendation**: 
- Use **Volume** if you want files on the server and don't need CDN
- Use **Spaces** if you want CDN (faster image loading) and better scalability

---

## Alternative: Hybrid Approach

You can use **both**:
- **Volume** for product files (Excel/PDF downloads)
- **Spaces** for images (thumbnails/features) - faster with CDN

Just configure both and use different disks for different file types in your code.

