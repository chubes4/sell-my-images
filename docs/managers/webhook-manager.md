# Webhook Manager

The Webhook Manager provides a shared routing and utility layer for external service webhooks.

Its primary goal is to make webhook handling **reliable** (by using `parse_request`) and **centralized** (so Stripe/Upsampler handlers don’t duplicate boilerplate).

## Endpoint Shape

Webhook requests are handled at:

- `/smi-webhook/{service}/`

Examples:

- `/smi-webhook/stripe/`
- `/smi-webhook/upsampler/`

## Routing Model (parse_request)

The manager hooks:

- `parse_request`

and checks the request URI against the webhook pattern. If a registered handler exists for the `{service}`, the corresponding callable is invoked. Unregistered services return `404`.

Core router:

- `SellMyImages\Managers\WebhookManager::handle_webhook_parse_request( $wp )`

## Registering Handlers

Handlers are registered by service name:

- `SellMyImages\Managers\WebhookManager::register_webhook( $service, $handler )`

Registered services can be inspected via:

- `SellMyImages\Managers\WebhookManager::get_registered_services()`

## Shared Utilities

The manager includes shared helper methods used by webhook handlers:

- `verify_webhook_security( $required_method = 'POST', $required_content_type = null )`
- `read_webhook_payload( $max_size = null )`
  - Default size limit comes from `SellMyImages\Config\Constants::MAX_WEBHOOK_PAYLOAD_SIZE`
  - Filter: `smi_max_webhook_payload_size`
- `send_webhook_response( $data = array( 'status' => 'received' ), $status_code = 200 )`
- `send_webhook_error( $message = '', $status_code = 400 )`

These helpers intentionally focus on request shape/payload safety; service-specific signature validation (Stripe/Upsampler) is implemented in the individual webhook handlers.

## Related Code

- `sell-my-images/src/Managers/WebhookManager.php`
- `sell-my-images/docs/configuration/webhook-endpoints.md`
