# Clean Unused Images — PrestaShop

Remove product images that are no longer linked to any product, combination or shop assignment. Useful after bulk imports via CSV, migrations or product deletions that leave behind unlinked image files and/or database records.

> ⚠️ **Always back up your `img/p/` folder and database before running clean mode.**

---

## Compatibility

- PrestaShop 1.7.x
- PrestaShop 8.x

---

## Installation

Upload `cleanImages.php` to the root of your PrestaShop installation (same level as `index.php`).

---

## Usage

Open the script in your browser:

```
https://yourshop.com/cleanImages.php
```

### Modes

| Mode | URL | Description |
|------|-----|-------------|
| 🔍 Soft | `?mode=soft` | Preview only. Shows what would be removed and total disk space. No changes made. |
| 🗑️ Clean | `?mode=clean` | Executes the deletion. Removes files and DB records. |

**Always run soft mode first** and review the list before switching to clean mode.

---

## What it detects

Images that meet **all** of the following conditions:

- Not set as cover in `image_shop`
- Not linked to any product combination (`product_attribute_image`)
- Assigned to shop ID 1

---

## Output

The script shows:

- Total images in database
- Number of unused images found
- Number of folders affected
- **Total disk space freed** (or to be freed in soft mode)
- Detailed table per image: ID, product ID, type (DB + files / files only), file count, size and path

---

## Limitations

- Filters by `id_shop = 1` — **multishop setups** may need to adjust the query
- Third-party modules that manage images outside standard PrestaShop tables may have their images incorrectly flagged

---

## Delete after use

Remove the script from your server once the cleanup is done.

```bash
rm cleanImages.php
```
