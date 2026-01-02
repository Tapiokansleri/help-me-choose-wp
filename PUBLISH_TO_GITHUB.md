# Publish to GitHub - Commands

Run these commands in your terminal to publish the plugin to GitHub:

```bash
# Navigate to the plugin directory
cd /home/kansleri/www/uusi-energio/wp-content/plugins/help-me-choose-wp

# Initialize git repository
git init

# Add all files
git add .

# Create initial commit
git commit -m "Initial commit - Help me choose WordPress plugin v1.0.0"

# Set main branch
git branch -M main

# Add GitHub remote (if not already added)
git remote add origin https://github.com/Tapiokansleri/help-me-choose-wp.git

# Push to GitHub
git push -u origin main
```

## If you get authentication errors:

If GitHub asks for authentication, you can:

1. **Use SSH instead** (if you have SSH keys set up):
   ```bash
   git remote set-url origin git@github.com:Tapiokansleri/help-me-choose-wp.git
   git push -u origin main
   ```

2. **Use Personal Access Token**:
   - Go to GitHub → Settings → Developer settings → Personal access tokens → Tokens (classic)
   - Generate a new token with `repo` scope
   - Use the token as password when pushing

## After Publishing:

1. Go to https://github.com/Tapiokansleri/help-me-choose-wp
2. Verify all files are uploaded
3. Create your first release:
   - Click "Releases" → "Create a new release"
   - Tag: `v1.0.0`
   - Title: `Version 1.0.0`
   - Description: Add changelog
   - Click "Publish release"
   - GitHub Actions will automatically create a ZIP file

## Verify Updates Work:

After creating the release, WordPress should detect updates. The updater is already configured with:
- Owner: `Tapiokansleri`
- Repo: `help-me-choose-wp`

