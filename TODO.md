# To do

What is planned for the extra modules. Each new module or feature is
discussed before it is written.

## To fix

- **Not yet tried against real providers**: the S3 and FTPS drivers of
  ub_storage were tested against local servers only.

## New modules and features

- **More drivers for ub_storage**: Google Cloud Storage and Azure Blob
  each speak a language of their own.
- **SSH and SFTP**: running commands on another server, and SFTP as a
  driver of ub_storage. Both need the `ssh2` extension, which is rarely
  installed.
- **SOAP**, as a module around PHP's own SOAP classes.
- **WebSockets.** As large as the queue: a server that keeps running.
  Server-Sent Events do "the server tells the browser" with far less.
- **More of AWS** than S3.
- **Queue.** A request stores a job ("send this email") and answers at
  once; a worker process does the work, tries again on failure and keeps
  a list of failed jobs. Large, and easy to get wrong: two workers must
  never take the same job, and a worker that dies must not lose one.
