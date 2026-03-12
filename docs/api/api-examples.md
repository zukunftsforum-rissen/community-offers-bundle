# API Examples -- Door Access System

## Tür öffnen

``` bash
curl -X POST https://example.de/api/door/open/workshop
```

## Poll

``` bash
curl https://example.de/api/device/poll/pi01?areas=workshop
```

## Confirm Success

``` bash
curl -X POST https://example.de/api/device/confirm/pi01 \
  -H "Content-Type: application/json" \
  -d '{"jobId":123,"nonce":"abc","ok":true}'
```

## Confirm Timeout (Beispiel)

HTTP 410

``` json
{
  "success": false,
  "error": "confirm_timeout"
}
```

## Rate Limit Beispiel

HTTP 429

``` json
{
  "success": false,
  "error": "rate_limited",
  "retryAfterSeconds": 12
}
```
