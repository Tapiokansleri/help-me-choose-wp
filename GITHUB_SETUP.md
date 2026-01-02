# GitHub Setup Guide for WordPress Updates

This guide will help you set up GitHub repository management and enable automatic WordPress updates for this plugin.

## Step 1: Create GitHub Repository

1. Go to [GitHub](https://github.com) and create a new repository
2. Name it (e.g., `auta-minua-valitsemaan`)
3. Make it **private** or **public** (your choice)
4. **Do NOT** initialize with README, .gitignore, or license (we already have these)

## Step 2: Initialize Git and Push to GitHub

```bash
cd /home/kansleri/www/uusi-energio/wp-content/plugins/auta-minua-valitsemaan
git init
git add .
git commit -m "Initial commit"
git branch -M main
git remote add origin https://github.com/YOUR_USERNAME/YOUR_REPO_NAME.git
git push -u origin main
```

Replace `YOUR_USERNAME` and `YOUR_REPO_NAME` with your actual GitHub username and repository name.

## Step 3: Configure Updater

1. Open `auta-minua-valitsemaan.php`
2. Find the `amv_init_updater()` function (around line 70)
3. Replace `YOUR_GITHUB_USERNAME` with your GitHub username
4. Replace `YOUR_REPO_NAME` with your repository name

Example:
```php
$github_owner = 'tapiokauranen';
$github_repo = 'auta-minua-valitsemaan';
```

## Step 4: Create Your First Release

To enable updates, you need to create a GitHub Release:

1. Go to your GitHub repository
2. Click on "Releases" → "Create a new release"
3. Tag version: `v1.0.0` (must start with 'v')
4. Release title: `Version 1.0.0`
5. Description: Add changelog notes
6. Click "Publish release"

The GitHub Actions workflow will automatically create a ZIP file and attach it to the release.

## Step 5: Testing Updates

1. Make a change to the plugin
2. Update the version number in `auta-minua-valitsemaan.php`:
   - Change `Version: 1.0.0` to `Version: 1.0.1`
   - Change `define('AMV_VERSION', '1.0.0');` to `define('AMV_VERSION', '1.0.1');`
3. Commit and push:
   ```bash
   git add .
   git commit -m "Version 1.0.1"
   git push
   ```
4. Create a new release with tag `v1.0.1`
5. WordPress will detect the update and show it in the Plugins page

## How It Works

- The updater checks GitHub Releases API every 12 hours
- When a new version is detected, WordPress shows an update notification
- Users can update directly from WordPress admin
- The updater downloads the ZIP file from GitHub Releases

## Important Notes

- **Version numbers**: Always use semantic versioning (e.g., 1.0.0, 1.0.1, 1.1.0)
- **Release tags**: Must start with 'v' (e.g., v1.0.0)
- **ZIP files**: The GitHub Actions workflow automatically creates a ZIP file for each release
- **Private repos**: If your repository is private, you'll need to use a Personal Access Token (see below)

## Private Repository Setup (Optional)

If your repository is private, you need to add authentication:

1. Create a GitHub Personal Access Token:
   - Go to GitHub → Settings → Developer settings → Personal access tokens → Tokens (classic)
   - Generate new token with `repo` scope
   - Copy the token

2. Add token to WordPress:
   - Install a plugin like "GitHub Updater" or modify the updater class to use the token
   - Or use SSH keys for authentication

## Troubleshooting

- **Updates not showing**: Clear WordPress transients or wait 12 hours
- **Download fails**: Check GitHub repository permissions and release tags
- **Version mismatch**: Ensure version in plugin file matches release tag (without 'v')

