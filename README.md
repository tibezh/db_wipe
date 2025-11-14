# DB Wipe

Plugin-based system for deleting data from Drupal databases.

## ⚠️ WARNING

**THIS MODULE PERMANENTLY DELETES DATA!**

- **ALWAYS** backup your database first
- **ALWAYS** use `--dry-run` to preview deletions
- Deleted data cannot be recovered

## Modules

### db_wipe (Core)
Plugin system only. Does not delete anything by itself.

### db_wipe_entity
Deletes entities using Drupal's entity API.
- Uses `->delete()` method
- Fires entity hooks and events
- Slow but safe
- Use for production content

### db_wipe_db
Truncates database tables directly.
- Uses SQL `TRUNCATE`
- Very fast
- Bypasses entity API
- Use for logs, cache, sessions

## Installation

```bash
# Core (required)
drush en db_wipe

# Entity deletion
drush en db_wipe_entity

# Table truncation
drush en db_wipe_db
```

## Usage

### Entity Deletion

```bash
# Delete nodes
drush db:wipe-entities node --bundle=article

# Preview first (recommended)
drush db:wipe-entities node --dry-run

# Exclude specific IDs
drush db:wipe-entities node --exclude-ids=1,2,3

# Only specific IDs
drush db:wipe-entities node --include-ids=100,101,102

# Skip batch processing
drush db:wipe-entities node --no-batch

# Skip confirmation (dangerous!)
drush db:wipe-entities node --yes
```

### Table Truncation

```bash
# List safe tables
drush db:wipe-list-tables

# Truncate a table
drush db:wipe-table watchdog

# Preview first
drush db:wipe-table sessions --dry-run

# Skip confirmation (dangerous!)
drush db:wipe-table cache_bootstrap --yes
```

### Safe Tables

Only these tables can be truncated:
- `watchdog` - System logs
- `sessions` - User sessions
- `flood` - Flood control
- `queue` - Queue items
- `batch` - Batch data
- `semaphore` - Lock data
- `cache_*` - All cache tables

## Comparison

| | Entity Wipe | DB Wipe |
|---|---|---|
| Speed | Slow | Very Fast |
| Safety | High | Medium |
| Entity Hooks | Yes | No |
| Batch Processing | Yes | No |
| Target | Entities | Tables |
| Use Case | Production | Dev/Logs |

## Safety Features

### Entity Wipe
- User ID 1 always protected
- Batch processing (50 entities per batch)
- Event system
- Dry-run mode

### DB Wipe
- Whitelist-only tables
- Must type table name to confirm
- Row count display
- Event system
- Dry-run mode

## Events

### Entity Events
- `db_wipe_entity.before_wipe` - Before deletion
- `db_wipe_entity.after_wipe` - After deletion
- `db_wipe_entity.batch_process` - During batch

### Database Events
- `db_wipe_db.before_truncate` - Before truncate
- `db_wipe_db.after_truncate` - After truncate

## Custom Plugin Example

```php
<?php

namespace Drupal\my_module\Plugin\Wipe;

use Drupal\db_wipe\Plugin\WipePluginBase;

/**
 * @WipePlugin(
 *   id = "my_wipe",
 *   label = @Translation("My Wipe"),
 *   description = @Translation("Custom wipe strategy"),
 *   category = "custom",
 *   weight = 20
 * )
 */
class MyWipePlugin extends WipePluginBase {

  public function supports($target): bool {
    return TRUE;
  }

  public function execute($target, array $options = []): array {
    return [
      'success' => TRUE,
      'count' => 100,
      'message' => 'Done',
      'prevented' => FALSE,
    ];
  }

  public function preview($target, array $options = []): array {
    return [
      'count' => 100,
      'items' => [],
      'details' => [],
    ];
  }

}
```

## Event Subscriber Example

```php
<?php

namespace Drupal\my_module\EventSubscriber;

use Drupal\db_wipe_entity\Event\BeforeEntityWipeEvent;
use Drupal\db_wipe_entity\Event\EntityWipeEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class MyEventSubscriber implements EventSubscriberInterface {

  public static function getSubscribedEvents(): array {
    return [
      EntityWipeEvents::BEFORE_WIPE => ['onBeforeWipe', 0],
    ];
  }

  public function onBeforeWipe(BeforeEntityWipeEvent $event): void {
    // Prevent deletion
    if ($event->getBundle() === 'important') {
      $event->preventWipe();
    }

    // Modify query
    $query = $event->getQuery();
    $query->condition('status', 1);

    // Log
    \Drupal::logger('my_module')->warning('Deleting @count entities', [
      '@count' => $event->getCount(),
    ]);
  }

}
```

## Best Practices

1. Always use `--dry-run` first
2. Always backup database
3. Never use `--yes` without review
4. Test on dev/staging first
5. Choose correct module:
   - Production content → db_wipe_entity
   - Logs/cache → db_wipe_db
6. Subscribe to events for audit trails
7. Monitor logs after deletion

## Requirements

- Drupal 10 or 11
- Drush 12+
- PHP 8.1+

## License

GPL-2.0+
