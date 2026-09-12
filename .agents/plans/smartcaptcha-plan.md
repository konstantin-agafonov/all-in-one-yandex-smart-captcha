# План: Универсальный плагин Яндекс SmartCaptcha для WordPress

## Контекст

**Цель:** создать переиспользуемый плагин `all-in-one-yandex-smart-captcha`, который можно загрузить в репозиторий плагинов WordPress и подключить к любому сайту. Плагин автоматически защищает все формы на странице (кастомные + Contact Form 7).

**Режим:** невидимый (invisible captcha)  
**Хранение ключей:** через страницу настроек плагина в админке  
**Текущий сайт:** тема franchbiz — 3 кастомных AJAX-формы + 2-3 CF7-формы

### Текущие формы на сайте

| # | Форма | Тип | Эндпоинт |
|---|-------|-----|----------|
| 1 | `#presentation-form` | Custom AJAX | `send-presentation.php` |
| 2 | `#presentationForm` | Custom AJAX | `send-pres-channel_single.php` |
| 3 | `#projectForm` | Custom AJAX | `send-pres-channel_single.php` |
| 4 | CF7 «Подписка на рассылку» (ID: 0a41a39) | CF7 AJAX | CF7 internal |
| 5 | CF7 «Форма заявок» (ID: b9dbc77) | CF7 AJAX | CF7 internal |

---

## Архитектура плагина

```
all-in-one-yandex-smart-captcha/
├── all-in-one-yandex-smart-captcha.php    # Главный файл с хедером
├── includes/
│   ├── class-smartcaptcha-core.php        # Ядро: проверка токена, хуки
│   └── class-smartcaptcha-admin.php       # Логика настроек (контроллер)
├── template-parts/
│   └── admin/
│       ├── settings-page.php              # Обёртка страницы настроек (форма + кнопка)
│       ├── field-checkbox.php             # Переиспользуемое поле-чекбокс
│       ├── field-text.php                 # Переиспользуемое поле text/password
│       └── usage-instructions.php         # Блок «Использование в теме»
├── public/
│   └── js/
│       └── smartcaptcha-front.js          # Клиентский JS: инжект токена во все формы (sitekey через wp_localize_script)
├── languages/
│   └── all-in-one-yandex-smart-captcha.pot
├── readme.txt
└── uninstall.php
```

### Найденные переиспользуемые паттерны разметки

| Паттерн | Где встречается | Кол-во | Решение |
|---------|-----------------|--------|---------|
| `<p class="description">...</p>` | Поле checkbox + поле text | 2 | Выносится в шаблон поля |
| Чекбокс с label + description | `enabled`, `cf7_auto` | 2 | Один шаблон `field-checkbox.php` |
| Text/password input + description | `sitekey`, `secret` | 2 | Один шаблон `field-text.php` |
| `<div class="wrap"><h1><form>...</form></div>` | Страница настроек | 1 | Шаблон `settings-page.php` |
| Блок «Использование в теме» | Страница настроек | 1 | Шаблон `usage-instructions.php` |

---

## Шаг 1. Главный файл плагина

**Файл:** `all-in-one-yandex-smart-captcha.php`

```php
<?php
/**
 * Plugin Name:       All-in-One Yandex SmartCaptcha
 * Plugin URI:        https://example.com/all-in-one-yandex-smart-captcha
 * Description:       Защита всех форм на сайте Яндекс SmartCaptcha (invisible). Автоматически инжектит токен в формы, интеграция с Contact Form 7.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Developer
 * License:           GPL v2 or later
 * Text Domain:       all-in-one-yandex-smart-captcha
 * Domain Path:       /languages
 */

defined('ABSPATH') || exit;

define('AIOYSC_VERSION', '1.0.0');
define('AIOYSC_FILE', __FILE__);
define('AIOYSC_PATH', plugin_dir_path(__FILE__));
define('AIOYSC_URL', plugin_dir_url(__FILE__));
define('AIOYSC_BASENAME', plugin_basename(__FILE__));

require_once AIOYSC_PATH . 'includes/class-smartcaptcha-core.php';
require_once AIOYSC_PATH . 'includes/class-smartcaptcha-admin.php';

register_activation_hook(__FILE__, function () {
    if (!get_option('aioysc_settings')) {
        add_option('aioysc_settings', [
            'enabled'   => true,
            'sitekey'   => '',
            'secret'    => '',
            'mode'      => 'invisible',
            'cf7_auto'  => true,
        ]);
    }
});

register_deactivation_hook(__FILE__, function () {
    // nothing to clean up
});

add_action('plugins_loaded', function () {
    AIOYSC\Core::get_instance();
    if (is_admin()) {
        AIOYSC\Admin::get_instance();
    }
});
```

---

## Шаг 2. Ядро плагина — серверная проверка + подключение JS

**Файл:** `includes/class-smartcaptcha-core.php`

```php
<?php
namespace AIOYSC;

defined('ABSPATH') || exit;

class Core {
    private static ?self $instance = null;

    public static function get_instance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);

        // Автоинтеграция с CF7
        add_filter('wpcf7_spam', [$this, 'cf7_spam_check']);
    }

    /**
     * Подключение JS SmartCaptcha на фронтенде.
     * Не подключается если ключ или секрет пусты — форма работает без капчи.
     */
    public function enqueue_assets(): void {
        $settings = get_option('aioysc_settings', []);
        if (empty($settings['enabled']) || empty($settings['sitekey']) || empty($settings['secret'])) {
            return;
        }

        wp_enqueue_script(
            'yandex-smartcaptcha',
            'https://smartcaptcha.cloud.yandex.ru/captcha.js?render=onload&onload=onloadSmartcaptcha',
            [],
            null,
            true
        );

        wp_enqueue_script(
            'smartcaptcha-front',
            AIOYSC_URL . 'public/js/smartcaptcha-front.js',
            ['yandex-smartcaptcha'],
            AIOYSC_VERSION,
            true
        );

        wp_localize_script('smartcaptcha-front', 'smartcaptchaConfig', [
            'sitekey' => $settings['sitekey'],
        ]);
    }

    /**
     * Публичная функция верификации для тем/плагинов.
     * Использование: if ( ! AIOYSC\Core::verify_token() ) { ... }
     *
     * @return true если токен валиден, false если нет
     */
    public static function verify_token(): bool {
        $token = $_POST['smartcaptcha_token'] ?? '';

        if (empty($token)) {
            return false;
        }

        $settings = get_option('aioysc_settings', []);
        $secret = $settings['secret'] ?? '';

        if (empty($secret)) {
            error_log('SmartCaptcha: секретный ключ не задан в настройках плагина');
            return false;
        }

        $ip = self::get_client_ip();

        $response = wp_remote_post('https://smartcaptcha.cloud.yandex.ru/validate', [
            'body'    => wp_json_encode([
                'secret' => $secret,
                'token'  => $token,
                'ip'     => $ip,
            ]),
            'headers' => ['Content-Type' => 'application/json'],
            'timeout' => 5,
        ]);

        if (is_wp_error($response)) {
            error_log('SmartCaptcha: ошибка запроса — ' . $response->get_error_message());
            return false;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        return isset($body['status']) && $body['status'] === 'ok';
    }

    /**
     * Получение IP клиента (совместимо с прокси)
     */
    private static function get_client_ip(): string {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return sanitize_text_field(wp_unslash($_SERVER['HTTP_CLIENT_IP']));
        }
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            return sanitize_text_field(wp_unslash(trim($ips[0])));
        }
        return isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
    }

    /**
     * CF7: автоматическая проверка токена
     * Помечает форму как спам если токен невалиден
     */
    public function cf7_spam_check(bool $spam): bool {
        if (!$spam) {
            $token = $_POST['smartcaptcha_token'] ?? '';
            if (!empty($token)) {
                $spam = !self::verify_token();
            }
        }
        return $spam;
    }
}
```

---

## Шаг 3. Админка — контроллер (без разметки)

**Файл:** `includes/class-smartcaptcha-admin.php`

Класс-контроллер. Вся разметка вынесена в `template-parts/admin/`. Класс только:
- Регистрирует меню и настройки
- Передаёт данные в шаблоны через `$args`
- Вызывает `include()` шаблонов

```php
<?php
namespace AIOYSC;

defined('ABSPATH') || exit;

class Admin {
    private static ?self $instance = null;
    private const OPTION_GROUP = 'aioysc_settings';
    private const OPTION_NAME  = 'aioysc_settings';
    private const PAGE_SLUG    = 'aioysc-settings';

    public static function get_instance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_menu', [$this, 'add_menu']);
        add_action('admin_init', [$this, 'register_settings']);
    }

    public function add_menu(): void {
        add_options_page(
            'All In One Yandex SmartCaptcha',
            'Yandex SmartCaptcha',
            'manage_options',
            self::PAGE_SLUG,
            [$this, 'render_page']
        );
    }

    public function register_settings(): void {
        register_setting(
            self::OPTION_GROUP,
            self::OPTION_NAME,
            [
                'type'              => 'array',
                'sanitize_callback' => [$this, 'sanitize'],
                'default'           => [
                    'enabled'  => true,
                    'sitekey'  => '',
                    'secret'   => '',
                    'mode'     => 'invisible',
                    'cf7_auto' => true,
                ],
            ]
        );

        add_settings_section(
            'aioysc_main',
            'Настройки SmartCaptcha',
            null,
            self::PAGE_SLUG
        );

        add_settings_field('enabled', 'Включить', [$this, 'render_checkbox'], self::PAGE_SLUG, 'aioysc_main', [
            'key'  => 'enabled',
            'desc' => 'Включить или отключить защиту SmartCaptcha',
        ]);

        add_settings_field('sitekey', 'Site Key', [$this, 'render_text'], self::PAGE_SLUG, 'aioysc_main', [
            'key'  => 'sitekey',
            'desc' => 'Публичный ключ из кабинета Яндекса',
        ]);

        add_settings_field('secret', 'Secret Key', [$this, 'render_text'], self::PAGE_SLUG, 'aioysc_main', [
            'key'   => 'secret',
            'type'  => 'password',
            'desc'  => 'Секретный ключ из кабинета Яндекса',
        ]);

        add_settings_field('cf7_auto', 'CF7 автоинтеграция', [$this, 'render_checkbox'], self::PAGE_SLUG, 'aioysc_main', [
            'key'  => 'cf7_auto',
            'desc' => 'Автоматически проверять токен во всех формах Contact Form 7',
        ]);
    }

    public function sanitize(array $input): array {
        return [
            'enabled'  => !empty($input['enabled']),
            'sitekey'  => sanitize_text_field($input['sitekey'] ?? ''),
            'secret'   => sanitize_text_field($input['secret'] ?? ''),
            'mode'     => sanitize_text_field($input['mode'] ?? 'invisible'),
            'cf7_auto' => !empty($input['cf7_auto']),
        ];
    }

    /**
     * Рендер страницы настроек — подключает шаблон
     */
    public function render_page(): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        $args = [
            'option_group' => self::OPTION_GROUP,
            'page_slug'    => self::PAGE_SLUG,
        ];

        include AIOYSC_PATH . 'template-parts/admin/settings-page.php';
    }

    /**
     * Рендер чекбокса — подключает шаблон
     */
    public function render_checkbox(array $args): void {
        $options = get_option(self::OPTION_NAME, []);
        $args['value'] = $options[$args['key']] ?? false;

        include AIOYSC_PATH . 'template-parts/admin/field-checkbox.php';
    }

    /**
     * Рендер text/password — подключает шаблон
     */
    public function render_text(array $args): void {
        $options = get_option(self::OPTION_NAME, []);
        $args['value'] = $options[$args['key']] ?? '';
        $args['type']  = $args['type'] ?? 'text';

        include AIOYSC_PATH . 'template-parts/admin/field-text.php';
    }
}
```

---

## Шаг 4. Шаблоны — template-parts/admin/

### 4a. `template-parts/admin/settings-page.php`

Обёртка страницы настроек. Содержит: `<div class="wrap">`, заголовок, форму, кнопку «Сохранить», секцию «Использование в теме».

```php
<?php
/**
 * Страница настроек SmartCaptcha.
 *
 * Переменные:
 * @var string $option_group  Группа опций для settings_fields()
 * @var string $page_slug     Slug страницы для do_settings_sections()
 */

defined('ABSPATH') || exit;
?>
<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <form action="options.php" method="post">
        <?php
        settings_fields($option_group);
        do_settings_sections($page_slug);
        submit_button('Сохранить');
        ?>
    </form>

    <?php include AIOYSC_PATH . 'template-parts/admin/usage-instructions.php'; ?>
</div>
```

---

### 4b. `template-parts/admin/field-checkbox.php`

Переиспользуемое поле-чекбокс. Используется для настроек `enabled` и `cf7_auto`.

```php
<?php
/**
 * Поле-чекбокс настроек.
 *
 * Переменные:
 * @var string  $key   Ключ опции (имя поля)
 * @var string  $desc  Описание под полем
 * @var mixed   $value Текущее значение (bool)
 */

defined('ABSPATH') || exit;

$name = AIOYSC\Admin::OPTION_NAME . '[' . $args['key'] . ']';
?>
<input type="checkbox"
       id="<?php echo esc_attr($args['key']); ?>"
       name="<?php echo esc_attr($name); ?>"
       value="1"
       <?php checked(!empty($args['value'])); ?>>
<?php if (!empty($args['desc'])): ?>
    <p class="description"><?php echo esc_html($args['desc']); ?></p>
<?php endif; ?>
```

---

### 4c. `template-parts/admin/field-text.php`

Переиспользуемое поле text/password. Используется для настроек `sitekey` и `secret`.

```php
<?php
/**
 * Поле text/password настроек.
 *
 * Переменные:
 * @var string  $key   Ключ опции (имя поля)
 * @var string  $desc  Описание под полем
 * @var string  $type  Тип input: 'text' или 'password'
 * @var string  $value Текущее значение
 */

defined('ABSPATH') || exit;

$name = AIOYSC\Admin::OPTION_NAME . '[' . $args['key'] . ']';
$type = $args['type'] ?? 'text';
?>
<input type="<?php echo esc_attr($type); ?>"
       id="<?php echo esc_attr($args['key']); ?>"
       name="<?php echo esc_attr($name); ?>"
       value="<?php echo esc_attr($args['value']); ?>"
       class="regular-text">
<?php if (!empty($args['desc'])): ?>
    <p class="description"><?php echo esc_html($args['desc']); ?></p>
<?php endif; ?>
```

---

### 4d. `template-parts/admin/usage-instructions.php`

Блок с инструкцией по использованию в теме. Переиспользуется как отдельная секция.

```php
<?php
/**
 * Блок «Использование в теме» на странице настроек.
 */

defined('ABSPATH') || exit;
?>
<hr>
<h3>Использование в теме</h3>
<p>В обработчике AJAX-формы добавьте:</p>
<pre><code><?php echo esc_html(
'if (class_exists(\'AIOYSC\\Core\') && !AIOYSC\Core::verify_token()) {
    wp_send_json_error([\'message\' => \'Проверка защиты не пройдена.\']);
    wp_die();
}'
); ?></code></pre>
<p class="description">
    Contact Form 7 интегрируется автоматически — фильтр <code>wpcf7_spam</code> подключается при активации плагина.
</p>
```

---

## Шаг 5. Клиентский JS — отдельный файл

**Файл:** `public/js/smartcaptcha-front.js`

Sitekey передаётся через `wp_localize_script()` в глобальный объект `smartcaptchaConfig`.

```js
function smartcaptchaProcessForm(form) {
    const existing = form.querySelector('input[name="smartcaptcha_token"]');
    if (existing) return;

    const container = document.createElement('div');
    container.className = 'smartcaptcha-container';
    container.style.display = 'none';
    form.appendChild(container);

    try {
        smartcaptcha.render(container, {
            sitekey: smartcaptchaConfig.sitekey,
            invisible: true,
            callback: function (token) {
                let input = form.querySelector('input[name="smartcaptcha_token"]');
                if (!input) {
                    input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'smartcaptcha_token';
                    form.appendChild(input);
                }
                input.value = token;
            },
        });
    } catch (e) {
        console.warn('SmartCaptcha render error:', e);
    }
}

window.onloadSmartcaptcha = function () {
    window.smartcaptchaReady = true;

    if (typeof smartcaptchaConfig === 'undefined' || !smartcaptchaConfig.sitekey) {
        return;
    }

    const cf7Selector = '.wpcf7-form';
    const defaultSelector = 'form:not(.wpcf7-form)';

    function processAllForms() {
        document.querySelectorAll(defaultSelector).forEach(smartcaptchaProcessForm);
        document.querySelectorAll(cf7Selector).forEach(smartcaptchaProcessForm);
    }

    if (document.readyState === 'complete') {
        processAllForms();
    } else {
        window.addEventListener('load', processAllForms);
    }
};
```

---

## Шаг 6. Интеграция с темой franchbiz

Кастомные AJAX-обработчики темы загружают WordPress через `wp-load.php`, поэтому плагин уже доступен. Достаточно добавить **5 строк** в каждый файл.

### `ajax/send-presentation.php` — после `require($_SERVER['DOCUMENT_ROOT'] . '/wp-load.php');`

```php
if (class_exists('AIOYSC\\Core') && !AIOYSC\Core::verify_token()) {
    echo json_encode(['success' => false, 'message' => 'Проверка защиты не пройдена. Попробуйте ещё раз.']);
    exit;
}
```

### `ajax/send-presentation-channel.php` — после `require`:

```php
if (class_exists('AIOYSC\\Core') && !AIOYSC\Core::verify_token()) {
    echo json_encode(['success' => false, 'message' => 'Проверка защиты не пройдена. Попробуйте ещё раз.']);
    exit;
}
```

### `ajax/send-pres-channel_single.php` — после `require`:

```php
if (class_exists('AIOYSC\\Core') && !AIOYSC\Core::verify_token()) {
    echo json_encode(['success' => false, 'message' => 'Проверка защиты не пройдена. Попробуйте ещё раз.']);
    exit;
}
```

### CF7 формы

Автоматически — плагин подключает фильтр `wpcf7_spam` в ядре. Никаких изменений в теме не нужно.

---

## Шаг 7. Клиентский JS — как работает автоматический инжект

JS-код в `public/js/smartcaptcha-front.js` делает следующее:

1. Ждёт загрузки SmartCaptcha SDK (`onloadSmartcaptcha`)
2. Находит **все формы** на странице через `document.querySelectorAll('form:not(.wpcf7-form)')`
3. Находит **все CF7 формы** через `document.querySelectorAll('.wpcf7-form')`
4. Для каждой формы:
   - Создаёт скрытый `<div class="smartcaptcha-container">` внутри формы
   - Рендерит invisible captcha в этот контейнер
   - При получении токена — создаёт/обновляет `<input type="hidden" name="smartcaptcha_token">` внутри формы
5. При отправке формы токен уходит вместе с остальными данными

**Результат:** JavaScript работает на 100% автоматически. Ни одну форму не пропустит. Разработчику темы нужно только вызвать `AIOYSC\Core::verify_token()` на сервере.

---

## Шаг 8. Что не нужно менять

- `wp-config.php` — ключи хранятся в БД через настройки плагина
- `functions.php` темы — ничего не добавляется
- Шаблоны темы — контейнеры для капчи создаются автоматически через JS
- `front/dist/main.js` — не пересобирается, изменения только на PHP-стороне

---

## Итого: файлы плагина

| Файл | Описание |
|------|----------|
| `all-in-one-yandex-smart-captcha.php` | Главный файл с хедером плагина, константы, подключение классов |
| `includes/class-smartcaptcha-core.php` | Ядро: серверная верификация, подключение JS-файлов, CF7 фильтр |
| `includes/class-smartcaptcha-admin.php` | Контроллер: регистрация настроек, подключение шаблонов |
| `template-parts/admin/settings-page.php` | Шаблон: обёртка страницы настроек (форма + кнопка) |
| `template-parts/admin/field-checkbox.php` | Шаблон: переиспользуемое поле-чекбокс |
| `template-parts/admin/field-text.php` | Шаблон: переиспользуемое поле text/password |
| `template-parts/admin/usage-instructions.php` | Шаблон: блок «Использование в теме» |
| `public/js/smartcaptcha-front.js` | Клиентский JS: инжект токена во все формы (sitekey через wp_localize_script) |
| `readme.txt` | Описание для каталога плагинов |
| `uninstall.php` | Очистка опций при удалении |
| `languages/all-in-one-yandex-smart-captcha.pot` | Шаблон переводов |

### Изменения в теме franchbiz (3 файла, по 5 строк в каждый)

| Файл | Действие |
|------|----------|
| `ajax/send-presentation.php` | Добавить `AIOYSC\Core::verify_token()` |
| `ajax/send-presentation-channel.php` | Добавить `AIOYSC\Core::verify_token()` |
| `ajax/send-pres-channel_single.php` | Добавить `AIOYSC\Core::verify_token()` |

---

## Преимущества перед старым планом

| Старый план (тема) | Новый план (плагин) |
|---------------------|----------------------|
| Ключи в `wp-config.php` | Ключи в БД через админку |
| Функция `franchbiz_verify_smartcaptcha()` с привязкой к теме | Публичный API `AIOYSC\Core::verify_token()` — универсальный |
| JS привязан к конкретным селекторам форм | JS автоматически находит ВСЕ формы на странице |
| Требует правок `functions.php` темы | Самостоятельный плагин, ничего в тему добавлять не нужно |
| CF7-интеграция в файле темы | CF7-интеграция в плагине, работает сразу |
| Нельзя переиспользовать на другом сайте | Залил zip-плагин → включил → работает |
| Нет админки | Страница настроек в WordPress |
| Вся разметка в PHP-классе | Разметка вынесена в `template-parts/`, переиспользуемые шаблоны |

---

## Открытые вопросы

1. **sitekey** — нужен ключ из кабинета Яндекса (задаётся в админке плагина)
2. **CF7-подход** — фильтр `wpcf7_spam` рекомендован (пометит как спам, не ломает флоу CF7)
3. **Кастомное сообщение CF7** — по умолчанию покажет «spam»; если нужно кастомное — потребуется доп. хук `wpcf7_additional_errors`
4. **Nonces** — кастомные AJAX-формы темы не используют WordPress nonces (проблема темы, не плагина). Рекомендуется добавить nonce-проверку в тему отдельно
