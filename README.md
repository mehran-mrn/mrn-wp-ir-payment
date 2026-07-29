# MRN پرداخت ایران

افزونه مستقل و حرفه‌ای پرداخت آنلاین و اقساطی ایران برای WooCommerce.

## امکانات

- یک تجربه یکپارچه در Checkout و انتخاب سرویس توسط خریدار
- سازگار با Checkout کلاسیک، Checkout Blocks و HPOS
- اتصال آماده به زرین‌پال، زیبال، وندار، پی‌پینگ، سپال و آقای پرداخت
- آداپتور REST عمومی برای PSPهای سازمانی
- آداپتور REST مخصوص BNPL و پرداخت اقساطی مانند اسنپ‌پی، دیجی‌پی، تارا و ازکی‌وام
- تبدیل امن واحدهای ریال، تومان، صد ریال و هزار تومان
- بازگشت امضاشده و تأیید idempotent
- وضعیت `unknown` برای خطای شبکه هنگام verify؛ سفارش در این حالت از دست نمی‌رود
- ثبت دفتر تراکنش و رویدادهای ماسک‌شده
- رمزنگاری credentialها با Sodium یا AES-256-GCM
- داشبورد RTL، فیلتر تراکنش، خروجی CSV و بررسی سلامت
- نگه‌داری داده مالی هنگام uninstall مگر با opt-in صریح

## پیش‌نیاز

- WordPress 6.4+
- PHP 7.4+
- WooCommerce 8.0+
- ارز فروشگاه یکی از `IRT`، `IRR`، `IRHR` یا `IRHT`

## نصب

پوشه `mrn-ir-payment` را در `wp-content/plugins` قرار دهید، افزونه را فعال کنید و از منوی
«MRN پرداخت» اطلاعات پذیرنده را وارد کنید. سپس در
`ووکامرس ← پیکربندی ← پرداخت‌ها` روش «MRN پرداخت ایران» را فعال نگه دارید.

## اتصال سرویس اقساطی یا سازمانی

در کارت «پرداخت اقساطی / BNPL» یا «درگاه سازمانی REST» این موارد را وارد کنید:

1. URL ایجاد و تأیید پرداخت
2. نام و مقدار هدر احراز هویت
3. قالب JSON درخواست و verify
4. مسیر dot-notation توکن، وضعیت موفق و کد مرجع در پاسخ
5. الگوی انتقال مانند `https://partner.example/pay/{{authority}}`

متغیرهای قالب:

| متغیر | توضیح |
| --- | --- |
| `{{transaction_id}}` | UUID عمومی تراکنش |
| `{{order_id}}` | شناسه سفارش |
| `{{amount_toman}}` | مبلغ صحیح به تومان |
| `{{amount_rial}}` | مبلغ صحیح به ریال |
| `{{callback_url}}` | نشانی بازگشت امضاشده |
| `{{mobile}}` | موبایل صورتحساب |
| `{{email}}` | ایمیل صورتحساب |
| `{{authority}}` | توکن ایجادشده؛ مخصوص verify و redirect |
| `{{callback.FIELD}}` | مقدار برگشتی سرویس در query/body |

نمونه request:

```json
{
  "amount": "{{amount_rial}}",
  "orderId": "{{order_id}}",
  "callbackUrl": "{{callback_url}}",
  "mobile": "{{mobile}}"
}
```

## توسعه درگاه اختصاصی

- `mrn_ir_payment_provider_definitions`: افزودن یا تغییر metadata و فیلدهای سرویس
- `mrn_ir_payment_gateway_instance`: جایگزینی instance آداپتور
- `mrn_ir_payment_paid`: پس از تأیید قطعی
- `mrn_ir_payment_failed`: پس از رد قطعی

آداپتور PHP باید `MRN\IranPayment\Contracts\GatewayInterface` را پیاده‌سازی کند.

## سیاست داده

Uninstall به‌طور پیش‌فرض جدول تراکنش‌ها را حذف نمی‌کند. برای حذف صریح همه داده‌ها،
پیش از uninstall ثابت زیر را در `wp-config.php` تعریف کنید:

```php
define( 'MRN_IR_PAYMENT_REMOVE_DATA', true );
```

## مجوز

GPL-2.0-or-later
