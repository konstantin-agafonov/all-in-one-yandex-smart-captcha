# План: Подключение Яндекс SmartCaptcha на все формы

## Контекст

**6 форм, 3 AJAX-эндпоинта, 1 CF7-хук:**

| # | Форма | Тип | Эндпоинт | Шаблон |
|---|-------|-----|----------|--------|
| 1 | `#presentation-form` | Custom AJAX | `send-presentation.php` | `page-leads.php:120` |
| 2 | `#presentationForm` | Custom AJAX | `send-pres-channel_single.php` | `template-parts/form-presentation.php` |
| 3 | `#projectForm` | Custom AJAX | `send-pres-channel_single.php` | `template-parts/form-presentation.php` |
| 4 | CF7 «Подписка на рассылку» (ID: 0a41a39) | CF7 AJAX | CF7 internal | `footer.php:7` |
| 5 | CF7 «Форма заявок» (ID: b9dbc77) | CF7 AJAX | CF7 internal | `footer.php:50` |
| 6 | CF7 (ID: 61930) | CF7 AJAX | CF7 internal | где-то на сайте |

> Форма `presentation-channel-form` (footer.php:106) — мёртвый код: JS ссылается на `#presentation-channel-form`, но такой формы нет в HTML.

**Режим:** невидимый (invisible)  
**Хранение ключа:** `wp-config.php`

---

## Шаг 1. Ключ в `wp-config.php`

Добавить:

```php
define( 'YANDEX_SMARTCAPTCHA_SECRET', 'секретный_ключ_из_кабинета' );
```

---

## Шаг 2. Серверная верификация — новый файл `inc/smartcaptcha.php`

Создать файл `inc/smartcaptcha.php` с функцией:

```php
function franchbiz_verify_smartcaptcha( $token ) {
    if ( empty( $token ) ) {
        return false;
    }

    $secret = defined('YANDEX_SMARTCAPTCHA_SECRET') ? YANDEX_SMARTCAPTCHA_SECRET : '';
    if ( empty( $secret ) ) {
        error_log('SmartCaptcha: YANDEX_SMARTCAPTCHA_SECRET не задан');
        return true; // dev-режим: пропускаем
    }

    $response = wp_remote_post( 'https://smartcaptcha.cloud.yandex.ru/validate', [
        'body'    => wp_json_encode([
            'secret' => $secret,
            'token'  => $token,
            'ip'     => fm_resolve_ip_address(),
        ]),
        'headers' => [ 'Content-Type' => 'application/json' ],
        'timeout' => 5,
    ] );

    if ( is_wp_error( $response ) ) {
        error_log('SmartCaptcha: ошибка запроса — ' . $response->get_error_message());
        return false;
    }

    $body = json_decode( wp_remote_retrieve_body( $response ), true );
    return isset( $body['status'] ) && $body['status'] === 'ok';
}
```

---

## Шаг 3. Подключение скрипта — `functions.php`

Добавить:

```php
add_action( 'wp_enqueue_scripts', 'franchbiz_smartcaptcha_script' );
function franchbiz_smartcaptcha_script() {
    wp_enqueue_script(
        'yandex-smartcaptcha',
        'https://smartcaptcha.cloud.yandex.ru/captcha.js?render=onload&onload=onloadSmartcaptcha',
        [],
        null,
        true
    );
}
```

---

## Шаг 4. Inline JS — `functions.php`

Добавить inline-скрипт (после скрипта smartcaptcha):

```php
add_action( 'wp_enqueue_scripts', 'franchbiz_smartcaptcha_inline' );
function franchbiz_smartcaptcha_inline() {
    $js = <<<'JS'
window.onloadSmartcaptcha = function() {
    window.smartcaptchaReady = true;

    // Кастомные формы
    var customForms = document.querySelectorAll('#presentation-form, #presentationForm, #projectForm');
    customForms.forEach(function(form) {
        var container = form.querySelector('.smartcaptcha-container');
        if (!container) {
            container = document.createElement('div');
            container.className = 'smartcaptcha-container';
            container.style.display = 'none';
            form.appendChild(container);
        }
        try {
            smartcaptcha.render(container, {
                sitekey: 'ключ_из_кабинета',
                invisible: true,
                callback: function(token) {
                    var input = form.querySelector('input[name="smartcaptcha_token"]');
                    if (!input) {
                        input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = 'smartcaptcha_token';
                        form.appendChild(input);
                    }
                    input.value = token;
                }
            });
        } catch(e) {
            console.warn('SmartCaptcha render error:', e);
        }
    });

    // CF7 формы
    var cf7Forms = document.querySelectorAll('.wpcf7-form');
    cf7Forms.forEach(function(form) {
        var container = form.querySelector('.smartcaptcha-container');
        if (!container) {
            container = document.createElement('div');
            container.className = 'smartcaptcha-container';
            container.style.display = 'none';
            form.appendChild(container);
        }
        try {
            smartcaptcha.render(container, {
                sitekey: 'ключ_из_кабинета',
                invisible: true,
                callback: function(token) {
                    var input = form.querySelector('input[name="smartcaptcha_token"]');
                    if (!input) {
                        input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = 'smartcaptcha_token';
                        form.appendChild(input);
                    }
                    input.value = token;
                }
            });
        } catch(e) {
            console.warn('SmartCaptcha render error:', e);
        }
    });
};
JS;
    wp_add_inline_script( 'yandex-smartcaptcha', $js, 'after' );
}
```

---

## Шаг 5. Верификация в 3 AJAX-обработчиках

Добавить в каждый файл после `require` и до обработки данных:

### `ajax/send-presentation.php` — после строки 3:
```php
require_once($_SERVER['DOCUMENT_ROOT'] . '/wp-content/themes/franchbiz/inc/smartcaptcha.php');
if ( !franchbiz_verify_smartcaptcha( $_POST['smartcaptcha_token'] ?? '' ) ) {
    echo json_encode(['success' => false, 'message' => 'Проверка защиты не пройдена. Попробуйте ещё раз.']);
    exit;
}
```

### `ajax/send-presentation-channel.php` — после строки 2:
```php
require_once($_SERVER['DOCUMENT_ROOT'] . '/wp-content/themes/franchbiz/inc/smartcaptcha.php');
if ( !franchbiz_verify_smartcaptcha( $_POST['smartcaptcha_token'] ?? '' ) ) {
    echo json_encode(['success' => false, 'message' => 'Проверка защиты не пройдена. Попробуйте ещё раз.']);
    exit;
}
```

### `ajax/send-pres-channel_single.php` — после строки 3:
```php
require_once($_SERVER['DOCUMENT_ROOT'] . '/wp-content/themes/franchbiz/inc/smartcaptcha.php');
if ( !franchbiz_verify_smartcaptcha( $_POST['smartcaptcha_token'] ?? '' ) ) {
    echo json_encode(['success' => false, 'message' => 'Проверка защиты не пройдена. Попробуйте ещё раз.']);
    exit;
}
```

---

## Шаг 6. CF7 интеграция — хук верификации

В `inc/smartcaptcha.php` добавить:

```php
add_filter( 'wpcf7_spam', 'franchbiz_cf7_smartcaptcha_spam_check' );
function franchbiz_cf7_smartcaptcha_spam_check( $spam ) {
    if ( ! $spam ) {
        $token = $_POST['smartcaptcha_token'] ?? '';
        $spam = !franchbiz_verify_smartcaptcha( $token );
    }
    return $spam;
}
```

---

## Шаг 7. Подключение в `functions.php`

Добавить в начало:

```php
require_once get_template_directory() . '/inc/smartcaptcha.php';
```

---

## Шаг 8. Не требуется

- Rebuild `front/dist/main.js` — не нужен, изменения только на PHP-стороне
- Изменение шаблонов — контейнеры для капчи создаются автоматически через JS

---

## Итого: файлы

| Файл | Действие |
|------|----------|
| `wp-config.php` | Добавить `define()` |
| `inc/smartcaptcha.php` | **Новый файл** — функция верификации + CF7-хук |
| `functions.php` | Подключить `inc/smartcaptcha.php` + enqueue скрипта + inline JS |
| `ajax/send-presentation.php` | Добавить проверку токена (2 строки) |
| `ajax/send-presentation-channel.php` | Добавить проверку токена (2 строки) |
| `ajax/send-pres-channel_single.php` | Добавить проверку токена (2 строки) |

---

## Открытые вопросы

1. **sitekey** — нужен ключ из кабинета Яндекса (для передачи в `smartcaptcha.render()`)
2. **CF7-подход** — фильтр `wpcf7_spam` рекомендован (пометит как спам, не ломает флоу CF7)
3. **Fallback** — если ключ не задан (dev-режим) — пропуск заложен в плане
4. **Кастомное сообщение CF7** — по умолчанию покажет «spam»; если нужно кастомное — потребуется доп. хук
