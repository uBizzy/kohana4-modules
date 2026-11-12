# What Is Hidden

A debug tool shows the inside of an application, and the inside holds passwords and keys. The bar is built so that a screenshot of it, or a colleague looking over your shoulder, sees none of them.

## Three rules

**1. Only in development.** The bar is never shown in another environment. This is in the code, not in a setting: there is no way to switch it on for a live site.

**2. Secret names are hidden.** A value is shown as `hidden ***` when its name contains one of these words:

```text
password  passwd  pwd  secret  token  authorization  cookie
api_key  apikey  private_key  hash_key  salt
credit_card  card_number  cvv  ssn
```

This counts everywhere a value has a name: GET and POST fields, headers, session data, config values, your own data, and at every depth inside arrays and objects.

```php
Profiler::add('Payment', ['amount' => 59.9, 'card_number' => '4111 1111 1111 1111']);
// Shown as: "amount" => float 59.9, "card_number" => hidden ***
```

The list is `Debug::$sensitive`, shared with the error page. Add the words of your application in the bootstrap:

```php
Debug::$sensitive[] = 'iban';
Debug::$sensitive[] = 'licence_key';
```

**3. Some things are never shown, whatever their name.**

| Never shown | Why |
|---|---|
| Values of cookies | A cookie value is a session or a login. The bar shows the names and the length of each value |
| The `Authorization` and `Cookie` headers | The same |
| Environment variables of the server, `phpinfo()` | They hold database passwords and keys of other services |
| Config groups you did not list | `database`, `encrypt` and `auth` hold credentials |
| Text values written inside SQL | `WHERE email = 'ana@example.com'` is shown as `WHERE email = '***'` |

## What you still have to watch

- **A secret under an innocent name is shown.** `$session->set('code', $reset_code)` is not hidden, because nothing in `code` says it is secret. Name such values so they match the list, or add the word to `Debug::$sensitive`.
- **Personal data is shown.** Names, email addresses and the like in the session, in POST fields and in your own data are there to be looked at while developing. Work with test data.
- **The Config panel shows what you list.** Values with a secret name are hidden, but do not list `database`, `encrypt` or `auth` at all.
- **`mask_sql` can be switched off** to see the full queries. Queries with prepared statements, the default, hold no values in any case.

## Under a Content-Security-Policy

The bar has no inline event handlers and no style attributes. Its one style block and its one script carry the nonce of the request, `Security::nonce()`, the same as the error page:

```php
$nonce = Security::nonce();

$this->response->headers('content-security-policy', "default-src 'self'; script-src 'nonce-$nonce'; style-src 'nonce-$nonce'");
```

With a policy like this the bar works; without the nonce in the policy the browser blocks it, as it should.
