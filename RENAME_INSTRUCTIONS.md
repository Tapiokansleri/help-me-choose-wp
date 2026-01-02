# Renaming Instructions

To rename the plugin folder and file, follow these steps:

## Step 1: Deactivate the Plugin

1. Go to WordPress Admin → Plugins
2. Find "Help me choose" (or "Auta minua valitsemaan")
3. Click "Deactivate"

## Step 2: Rename the Folder

Rename the plugin folder from:
- `auta-minua-valitsemaan` 

To:
- `help-me-choose-wp`

**On Linux/Mac:**
```bash
cd /home/kansleri/www/uusi-energio/wp-content/plugins/
mv auta-minua-valitsemaan help-me-choose-wp
```

**On Windows:**
- Use File Explorer to rename the folder

## Step 3: Rename the Main PHP File

Inside the renamed folder, rename:
- `auta-minua-valitsemaan.php` → `help-me-choose-wp.php`

**On Linux/Mac:**
```bash
cd help-me-choose-wp
mv auta-minua-valitsemaan.php help-me-choose-wp.php
```

**On Windows:**
- Use File Explorer to rename the file

## Step 4: Activate the Plugin

1. Go to WordPress Admin → Plugins
2. Find "Help me choose"
3. Click "Activate"

## Step 5: Verify

1. Go to Tools → Help me choose
2. Verify everything works correctly
3. Check that the shortcode still works: `[auta_minua_valitsemaan]`

## Note

The shortcode `[auta_minua_valitsemaan]` remains the same for backward compatibility. The text domain also remains `auta-minua-valitsemaan` to preserve translations.

