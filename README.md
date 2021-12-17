# ElectronicCourseReserve Plugin

The key words "MUST", "MUST NOT", "REQUIRED", "SHALL", "SHALL NOT", "SHOULD",
"SHOULD NOT", "RECOMMENDED", "MAY", and "OPTIONAL"
in this document are to be interpreted as described in
[RFC 2119](https://www.ietf.org/rfc/rfc2119.txt).

**Table of Contents**

* [Requirements](#requirements)
* [Install](#install)
  * [Composer](#composer)
* [Logging](#logging)
* [Dependencies](#dependencies)
  * [GnuPG](#gnupg)
    * [Key Pair Generation](#key-pair-generation)
    * [Public Key Export](#public-key-export)
* [Interfaces](#interfaces)
    * [](#signature-creationverification)
    * [](#delivery-of-digitized-media-an-links-to-ilias)
* [License](#license)

## Requirements

* PHP: [![Minimum PHP Version](https://img.shields.io/badge/Minimum_PHP-7.3.x-blue.svg)](https://php.net/) [![Maximum PHP Version](https://img.shields.io/badge/Maximum_PHP-7.4.x-blue.svg)](https://php.net/)
* ILIAS: [![Minimum ILIAS Version](https://img.shields.io/badge/Minimum_ILIAS-7.x-orange.svg)](https://ilias.de/) [![Maximum ILIAS Version](https://img.shields.io/badge/Maximum_ILIAS-7.x-orange.svg)](https://ilias.de/)

## Install

This plugin MUST be installed as a
[User Interface Plugin](https://www.ilias.de/docu/goto_docu_pg_39405_42.html).

The files MUST be saved in the following directory:

	<ILIAS>/Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/ElectronicCourseReserve

Correct file and folder permissions MUST be
ensured by the responsible system administrator.

The plugin's files and folder SHOULD NOT be created, 
as root.

### Composer

After the plugin files have been installed as described above,
please install the [`composer`](https://getcomposer.org/) dependencies:

```bash
cd Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/ElectronicCourseReserve
composer install --no-dev
```


## Logging

The ILIAS log file is used whenever the plugin considers something
to be important to log.

## Dependencies

* CronElectronicCourseReserve Plugin (https://github.com/DatabayAG/CronElectronicCourseReserve)
* GnuPG (https://wiki.ubuntuusers.de/GnuPG/)
  * **Important:** For newer GnuPG versions, please follow the steps mentioned here: https://d.sb/2016/11/gpg-inappropriate-ioctl-for-device-errors

### GnuPG

The plugin uses *GnuPG* for token generation. Therefore you MUST create a public/private key pair, located in a keyring of an arbitrary server directory you MUST define in the plugin administration.

In the following examples, */srv/www/esa.invorbereitung.de/data/esainvorbe/.esa* is used as directory, and *esa@databay.de* is used as email address. A directory of your choice MAY be used. This directory MUST be readable by the operating system user the PHP interpreter is executed with (www-data, www-run, etc.),

#### Key Pair Generation

    gpg --homedir /srv/www/esa.invorbereitung.de/data/esainvorbe/.esa --gen-key
  
 
  * If you get asked for selecting a kind, choose: **DSA and Elgamal**
  * Keysize: **2048**
  * Key Expiration: **0**
  * Realname und Comment (Example): Elektronischer Semesterapparat
  * Email Address (Example): esa@databay.de
  * Passphrase (Your Secret): *****

#### Public Key Export

    gpg --homedir /srv/www/esa.invorbereitung.de/data/esainvorbe/.esa --armor --export esa@databay.de > esa_pub_key.asc

The public key file *esa_pub_key.asc* has to be shared with the library.

In the ILIAS configuration screen you'll have to enter the following values in regards of the key:

* Absolute Server Path/HomeDirectory: /srv/www/esa.invorbereitung.de/data/esainvorbe/.esa 
* Fingerprint (Key): Enter the unique fingerprint of the relevant private key. A list of possible keys will be provided on the configuration screen if ILIAS is able to read/analyze the GnuPG files. 
* Passphrase: *****

#### Known Issues

If you are using new `gpg` versions, e.g. v. 2.2.x in Ubuntu 18.04 or above, please ensure
the following configuration files exists with the respective settings:

```bash
cat gpg-agent.conf

allow-loopback-pinentry
```

```bash
cat gpg.conf

use-agent
pinentry-mode loopback
```

**The configuration files MUST be located within the `gpg` directory configured in ILIAS.**

The PHP wrapper for `gpg` requires a running `gpg agent`, which provides interfaces
via UNIX sockets. Please make sure your file system supports UNIX sockets. If you
notice any issues, the following links might be useful:

* https://askubuntu.com/questions/777900/how-to-configure-gnupgs-s-gpg-agent-socket-location
* https://michaelheap.com/gpg-cant-connect-to-the-agent-ipc-connect-call-failed/

## Interfaces

### Signature Creation/Verification

When redirecting the actor (mostly a course admin or tutor) to the research system of the
library (to order digitized media), ILIAS appends some query parameters to the base URL configured in the global 
plugin administration.

1. ref_id: The ILIAS ref_id of the current course
2. usr_id: The current login/username of the actor
3. ts: A unix timestamp base on the current point in time
4. email: The current email address of the actor
5. iltitle (optional): An optional title of the current course
6. iltoken: A cryptographic signature of the data above

Algorithm:
1. Parameters 1 - 5 are joined with an empty string.
    * Example: -1root1585137094mjansen\[at\]databay\[dot\]deExample 
2. A (detached) [GnuPG](#gnupg) signature is created with this string (created in 1.) used as message.
3. The built signature is base64 encoded.
4. The encoded signature is appended to the research system URL as "iltoken". 

The search system has to verify the signature accordingly.

### Delivery of Digitized Media an Links to ILIAS

The library has to push it's generated files to the folder configured in the global plugin administration.

It is up to the concrete institutions and libraries how the files will be transferred (SCP, FTP, ...).

There MUST be a XML file describing a delivery. The file MUST be based on the XML schema file [here](./xsd/import.xsd).

## License

See LICENSE file in this repository.