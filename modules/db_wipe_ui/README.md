# DB Wipe UI

Web-based user interface for the DB Wipe module.

## ⚠️ CRITICAL WARNING

**THIS MODULE PROVIDES A UI TO PERMANENTLY DELETE DATA!**

Only grant access to **highly trusted administrators** who understand the consequences.

**AUTOMATIC PROTECTION:** User ID 1 (admin) is **ALWAYS** excluded from deletion to prevent system lockout.

## Installation

```bash
# Requires db_wipe module
drush en db_wipe_ui

# Grant permission (DANGEROUS - only to trusted admins!)
drush role:perm:add administrator 'administer entity wipe'
```

## Accessing the Interface

**Path:** `/admin/config/development/entity-wipe`

**Menu:** Configuration → Development → Entity Wipe

## Using the UI

### Step 1: Select Entities

**Entity Type:** Choose what to delete (Nodes, Taxonomy Terms, Users, Media, Comments)

**Bundle/Type:** (Optional) Filter by specific type (e.g., Article, Page, Tags)
- Automatically loads based on selected entity type
- Select "- All -" to include all bundles

**Filters:** (Optional, click to expand)
- **Exclude IDs:** `1,2,3` - Skip these entity IDs
- **Include Only These IDs:** `100,101,102` - Delete ONLY these IDs
- Cannot use both exclude and include at the same time

**Dry Run:** (Enabled by default) ✅ RECOMMENDED
- Shows what would be deleted without actually deleting
- Displays entity IDs in preview

### Step 2: Preview

Click **"Preview Deletion"** button

**With Dry Run enabled:**
- Shows count of entities
- Lists entity IDs (first 100)
- No actual deletion occurs
- Safe to test filters

**With Dry Run disabled:**
- Redirects to confirmation page

### Step 3: Confirm Deletion

**⚠️ Only appears when Dry Run is disabled**

You'll see:
1. **Critical Warning Banner** - Large, impossible to miss
2. **Deletion Summary** - Exact details of what will be deleted
3. **Confirmation Field** - Must type `DELETE` exactly (capital letters)
4. **Buttons:**
   - Red "Yes, DELETE permanently" button
   - "Cancel (Go back)" link

Type `DELETE` and click the button to proceed.

### Step 4: Batch Processing

Deletion runs in background using Drupal's batch API.

Progress bar shows current status.

Success message appears when complete.

## Usage Examples

### Example 1: Delete All Article Nodes

1. Entity Type: `Content (Nodes)`
2. Bundle/Type: `Article`
3. Dry Run: ✅ Enabled
4. Click "Preview Deletion" → Review IDs
5. Uncheck Dry Run
6. Click "Preview Deletion" → Confirmation page
7. Type `DELETE` → Confirm

### Example 2: Delete Specific Nodes

1. Entity Type: `Content (Nodes)`
2. Include Only These IDs: `100,101,102`
3. Preview first, then confirm

### Example 3: Delete All Tags

1. Entity Type: `Taxonomy Terms`
2. Bundle/Type: `Tags`
3. Preview first, then confirm

### Example 4: Delete Users (⚠️ DANGEROUS)

1. Entity Type: `Users`
2. Preview carefully (User ID 1 is automatically protected)
3. Confirm with extreme caution
4. Note: Even if you specify `--include-ids=1`, user 1 will NOT be deleted

## Safety Features

### Multiple Confirmations
✅ Two-step process (form → confirmation)
✅ Must explicitly disable Dry Run
✅ Must type "DELETE" exactly

### Visual Warnings
✅ Warning banner on main form
✅ Critical warning box on confirmation
✅ Special warnings for user deletion
✅ Red danger-styled button

### Technical Safeguards
✅ Session-based (prevents CSRF)
✅ Permission restricted (marked dangerous)
✅ Batch processing (prevents timeouts)
✅ Event dispatching (allows prevention)

### Preview Mode
✅ Shows exact entity IDs
✅ Displays total count
✅ No actual deletion
✅ Enabled by default

### User Protection
✅ User ID 1 automatically excluded
✅ Cannot be deleted even if explicitly included
✅ Prevents system lockout
✅ Built into service layer

## Permissions

**Permission:** `administer entity wipe`

**Flags:**
- `restrict access: TRUE` (Dangerous!)
- Only grant to trusted administrators

**Grant via UI:**
1. People → Permissions
2. Find "Administer Entity Wipe"
3. Check box for trusted role only
4. Save

**Grant via Drush:**
```bash
drush role:perm:add administrator 'administer entity wipe'
```

## Interface Details

### Main Form Features

- **AJAX Bundle Loading** - Bundles load when entity type changes
- **Collapsible Filters** - Clean interface, advanced options hidden
- **Dry Run Default** - Safety first, must explicitly disable
- **Clear Labels** - Helpful descriptions on all fields
- **Warning Banner** - Always visible at top

### Confirmation Form Features

- **ASCII Banner** - Large critical warning (like Drush)
- **Summary List** - All parameters clearly listed
- **Text Confirmation** - Must type DELETE to proceed
- **Danger Button** - Visually distinct red button
- **Easy Cancel** - Prominent cancel option

### Styling

Custom CSS provides:
- Warning message styling (orange border)
- Critical warning styling (red border)
- Monospace warning text (for readability)
- Danger button styling (red background)
- Responsive layout

## Comparison: UI vs Drush

| Feature | UI | Drush |
|---------|----|----|
| Two-step confirmation | ✅ Yes | ❌ No |
| Must type DELETE | ✅ Yes | ❌ No |
| Visual warnings | ✅ Yes | ✅ Yes |
| Dry run default | ✅ Yes | ✅ Yes |
| Preview entity IDs | ✅ Yes (in UI) | ✅ Yes (in CLI) |
| Session safety | ✅ Yes | N/A |
| Large datasets | ⚠️ May timeout | ✅ Better |
| Automation | ❌ No | ✅ Yes |
| Skip confirmation | ❌ No | ✅ --yes flag |

**Use UI for:**
- Interactive deletion with visual feedback
- When you want strongest safety features
- Learning what entities exist
- One-off deletion tasks

**Use Drush for:**
- Very large datasets
- Automated/scripted deletions
- CI/CD pipelines
- Faster batch processing

## Troubleshooting

**"Session expired"**
- Waited too long between steps
- Solution: Start over from main form

**"No entities found"**
- Filters don't match any entities
- Solution: Check entity type and bundle selection

**"Permission denied"**
- Missing "Administer Entity Wipe" permission
- Solution: Ask administrator to grant permission

**Batch timeout**
- Very large dataset
- Solution: Use smaller filters, or use Drush instead

**Can't type DELETE**
- Browser autocomplete interfering
- Solution: Type manually, don't paste

## Technical Integration

Uses `db_wipe.entity_wipe` service:
```php
$wipeService = \Drupal::service('db_wipe.entity_wipe');
```

Same events as Drush commands:
- BeforeEntityWipeEvent
- EntityWipeBatchEvent
- AfterEntityWipeEvent

Session storage via private tempstore:
```php
$tempstore = \Drupal::service('tempstore.private')->get('db_wipe_ui');
```

## Best Practices

1. ✅ **ALWAYS** use Dry Run first
2. ✅ **ALWAYS** backup database before disabling Dry Run
3. ✅ Review entity IDs in preview carefully
4. ✅ Test on staging environment first
5. ✅ Grant permission only to trusted administrators
6. ✅ Monitor batch processing completion
7. ✅ Check logs after deletion
8. ✅ User ID 1 is automatically protected (no need to manually exclude)

## Testing

The UI module includes comprehensive functional tests.

### Running Tests

```bash
# Using Drupal test runner
php core/scripts/run-tests.sh --verbose --color --module db_wipe_ui

# Using PHPUnit
vendor/bin/phpunit modules/custom/db_wipe/modules/db_wipe_ui/tests
```

### Test Coverage

**EntityWipeFormTest:**
- Form access (with/without permission)
- Form rendering and elements
- User protection message display
- Dry run preview functionality
- Validation (no entities found)
- Exclude IDs functionality
- Include IDs functionality
- Cannot use both exclude and include
- User ID 1 protection
- Redirect to confirmation
- Bundle options change with AJAX

**EntityWipeConfirmFormTest:**
- Confirmation form rendering
- Session data display
- Confirmation text required
- Must type "DELETE" exactly (case-sensitive)
- User protection message
- Cancel link functionality
- Session expiration handling
- Successful deletion flow
- Complete deletion summary
- Include/exclude IDs in summary

## For More Information

- **Drush Commands:** See `../../README.md`
- **Service API:** See `../../README.md`
- **Events:** See `../../README.md`
- **Core Tests:** See `../../tests/`

## License

GPL-2.0+
