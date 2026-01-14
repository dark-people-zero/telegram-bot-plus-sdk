# Upgrade Guide

## From v1.x to v2.0.0

### TelegramEvents namespace change

Update your imports:

```php
// Old
use Packages\DarkPeople\TelegramBot\Src\Contracts\TelegramEvents;

// New
use DarkPeople\TelegramBot\Src\Contracts\TelegramEvents;
