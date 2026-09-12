# План: Переключение шаблонов с `include` на `get_template_part()`

## Контекст

Сейчас шаблоны подключаются через `include AIOYSC_PATH . '...'`. Это работает, но:
- Нет стандартного WordPress-механизма подключения
- Невозможно переопределить шаблон из темы (для кастомизации)
- Переменные передаются через контекст вызова — легко потерять (как уже было с `$option_group`)

**Цель:** переключить все подключения шаблонов на `get_template_part()` с передачей данных через 4-й параметр `$args` (доступен с WordPress 5.5).

---

## Текущее состояние

| Место подключения | Шаблон | Переменные | Совместимость с `$args` |
|-------------------|--------|------------|------------------------|
| `Admin::render_page()` | `settings-page.php` | `$option_group`, `$page_slug` | ❌ Нужно переделать |
| `Admin::render_checkbox()` | `field-checkbox.php` | `$args` (key, desc, value) | ✅ Уже использует `$args` |
| `Admin::render_text()` | `field-text.php` | `$args` (key, desc, type, value) | ✅ Уже использует `$args` |
| `settings-page.php` | `usage-instructions.php` | нет переменных | ✅ Не нужны |

---

## Шаг 1. Исправить баг с `settings-page.php` + переключить на `get_template_part()`

**Файл:** `template-parts/admin/settings-page.php`

Текущий код обращается к `$option_group` и `$page_slug` напрямую. С `get_template_part()` данные приходят в `$args`.

**Было:**
```php
settings_fields( $option_group );
do_settings_sections( $page_slug );
```

**Стало:**
```php
settings_fields( $args['option_group'] );
do_settings_sections( $args['page_slug'] );
```

Также заменить вложенный `include` на `get_template_part()`:
```php
// Было:
include AIOYSC_PATH . 'template-parts/admin/usage-instructions.php';

// Стало:
get_template_part( AIOYSC_PATH . 'template-parts/admin/usage-instructions' );
```

> **Примечание:** `get_template_part()` ищет файлы в `wp-content/themes/`, но с полным путём в первом аргументе работает и для плагинов. Однако более чистый вариант — оставить `include` для вложенных шаблонов внутри плагина, а `get_template_part()` использовать только для точки входа.

---

## Шаг 2. Переключить `Admin::render_page()`

**Файл:** `includes/class-smartcaptcha-admin.php`, метод `render_page()`

**Было:**
```php
$args = [
    'option_group' => self::OPTION_GROUP,
    'page_slug'    => self::PAGE_SLUG,
];
include AIOYSC_PATH . 'template-parts/admin/settings-page.php';
```

**Стало:**
```php
get_template_part(
    AIOYSC_PATH . 'template-parts/admin/settings-page',
    null,
    [
        'option_group' => self::OPTION_GROUP,
        'page_slug'    => self::PAGE_SLUG,
    ]
);
```

---

## Шаг 3. Переключить `Admin::render_checkbox()`

**Файл:** `includes/class-smartcaptcha-admin.php`, метод `render_checkbox()`

**Было:**
```php
$options      = get_option( self::OPTION_NAME, [] );
$args['value'] = $options[ $args['key'] ] ?? false;
include AIOYSC_PATH . 'template-parts/admin/field-checkbox.php';
```

**Стало:**
```php
$options = get_option( self::OPTION_NAME, [] );
$args['value'] = $options[ $args['key'] ] ?? false;
get_template_part(
    AIOYSC_PATH . 'template-parts/admin/field-checkbox',
    null,
    $args
);
```

Шаблон `field-checkbox.php` уже использует `$args` — менять ничего не нужно.

---

## Шаг 4. Переключить `Admin::render_text()`

**Файл:** `includes/class-smartcaptcha-admin.php`, метод `render_text()`

**Было:**
```php
$options      = get_option( self::OPTION_NAME, [] );
$args['value'] = $options[ $args['key'] ] ?? '';
$args['type']  = $args['type'] ?? 'text';
include AIOYSC_PATH . 'template-parts/admin/field-text.php';
```

**Стало:**
```php
$options = get_option( self::OPTION_NAME, [] );
$args['value'] = $options[ $args['key'] ] ?? '';
$args['type']  = $args['type'] ?? 'text';
get_template_part(
    AIOYSC_PATH . 'template-parts/admin/field-text',
    null,
    $args
);
```

Шаблон `field-text.php` уже использует `$args` — менять ничего не нужно.

---

## Итого: что меняется

| Файл | Изменение |
|------|-----------|
| `includes/class-smartcaptcha-admin.php` | 3 метода: `include` → `get_template_part()` с `$args` |
| `template-parts/admin/settings-page.php` | `$option_group` → `$args['option_group']`, `$page_slug` → `$args['page_slug']` |
| `template-parts/admin/field-checkbox.php` | Без изменений (уже использует `$args`) |
| `template-parts/admin/field-text.php` | Без изменений (уже использует `$args`) |
| `template-parts/admin/usage-instructions.php` | Без изменений (нет переменных) |

---

## Совместимость

- Минимальная версия WordPress: 5.5 (4-й параметр `get_template_part()`)
- Текущий `Requires at least: 6.0` в хедере плагина — совместимость есть

---

## Риск

Низкий. Шаблоны `field-checkbox.php` и `field-text.php` уже построены на `$args` — они полностью совместимы с `get_template_part()`. Основная правка — `settings-page.php` (2 строки) и 3 метода в `Admin`.
