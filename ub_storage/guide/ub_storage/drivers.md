# Drivers

Each place of `config/ub_storage.php` names a driver and its settings.

```php
// application/config/ub_storage.php
return [
	'default' => 'local',

	'disks' => [
		'local'   => ['driver' => 'local', 'root' => APPPATH.'files'],
		'backups' => ['driver' => 's3', 'key' => '...', 'secret' => '...', 'region' => 'eu-west-1', 'bucket' => 'my-backups'],
	],
];
```

[!!] Keys and passwords do not belong in version control. Keep the config file with the real ones out of it.

| Driver | Keeps files | Needs |
|---|---|---|
| `local` | in a directory of the server | nothing |
| `ftp` | on an FTP server | the `ftp` extension of PHP |
| `s3` | in Amazon S3 or a service like it | the `curl` extension of PHP |

## local

```php
'local' => [
	'driver' => 'local',
	'root'   => APPPATH.'files',
],
```

The directory is made when it is not there.

- Keep it **outside the document root**. Inside it, every stored file can be asked for by its address, and a stored `.php` file would be run.
- A link inside the directory is not followed: a file is never read or written through one.

## ftp

```php
'ftp' => [
	'driver'   => 'ftp',
	'host'     => 'ftp.example.com',
	'username' => 'ana',
	'password' => '...',
	'ssl'      => true,
	'root'     => 'site/files',
],
```

| Setting | Default | |
|---|---|---|
| `host` | | name or address of the server |
| `port` | 21 | |
| `username`, `password` | anonymous | |
| `ssl` | false | FTPS: the login and the files are encrypted |
| `passive` | true | passive mode, which works through firewalls |
| `timeout` | 30 | seconds |
| `root` | | directory on the server; the one of the login when empty |

[!!] Plain FTP sends the password and every file readable by anybody on the way. Set `ssl` whenever the server offers it.

The connection is made the first time it is needed and kept until the request ends.

## s3

```php
's3' => [
	'driver' => 's3',
	'key'    => '...',
	'secret' => '...',
	'region' => 'eu-west-1',
	'bucket' => 'my-files',
	'root'   => 'site/files',
],
```

| Setting | Default | |
|---|---|---|
| `key`, `secret` | | the access key and its secret |
| `region` | us-east-1 | |
| `bucket` | | must exist already |
| `root` | | start of every key, to keep several storages in one bucket |
| `timeout` | 30 | seconds for each request |
| `endpoint` | Amazon | address of another service |
| `path_style` | true with an `endpoint` | the bucket in the path of the address instead of its host |
| `token` | | for keys that are lent for a while |

Nothing has to be installed: the module signs its requests itself (AWS Signature Version 4). The secret never leaves the server; what travels is a code made from it that fits one request only.

### Other services

Services that speak the language of S3 work with their address as `endpoint`:

```php
'spaces' => [
	'driver'   => 's3',
	'key'      => '...',
	'secret'   => '...',
	'region'   => 'ams3',
	'bucket'   => 'my-files',
	'endpoint' => 'https://ams3.digitaloceanspaces.com',
],
```

| Service | `endpoint` | `region` |
|---|---|---|
| MinIO | the address of your server | `us-east-1` |
| DigitalOcean Spaces | `https://<region>.digitaloceanspaces.com` | the region, such as `ams3` |
| Backblaze B2 | `https://s3.<region>.backblazeb2.com` | the region, such as `eu-central-003` |
| Cloudflare R2 | `https://<account>.r2.cloudflarestorage.com` | `auto` |
| Wasabi | `https://s3.<region>.wasabisys.com` | the region |

### Good to know

- A file is private unless the bucket says otherwise. Hand out a [temporary address](index#a-link-to-a-private-file) to let somebody download one.
- S3 has no directories, only names with `/` in them. `files('docs')` lists the names that start with `docs/`.
- Give the key the rights it needs and no more: to read, write, list and delete in this bucket.

## What is tested, and what is not

The tests of the module run every driver through the same list: storing, reading, listing, deleting, names with spaces and accents, large files, and every kind of path that must be refused.

- `local` is always tested.
- `ftp` is tested against a real FTP server.
- `s3` is tested against a server that checks the signature of every request, and its signatures are compared with the examples in Amazon's documentation.
- Not tested against the real thing: Amazon S3 itself and the services of the table above, and FTPS (`ssl`).
