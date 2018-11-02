# ElectronicCourseReserve Plugin

The key words "MUST", "MUST NOT", "REQUIRED", "SHALL", "SHALL NOT", "SHOULD",
"SHOULD NOT", "RECOMMENDED", "MAY", and "OPTIONAL"
in this document are to be interpreted as described in
[RFC 2119](https://www.ietf.org/rfc/rfc2119.txt).

**Table of Contents**

* [Install](#install)
* [Logging](#logging)
* [Dependencies](#dependencies)
  * [GnuPG](#gnupg)
    * [Key Pair Generation](#key-pair-generation)
    * [Public Key Export](#public-key-export)
* [License](#license)

## Install

This plugin MUST be installed as a
[User Interface Plugin](https://www.ilias.de/docu/goto_docu_pg_39405_42.html).

The files MUST be saved in the following directory:

	<ILIAS>/Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/ElectronicCourseReserve

Correct file and folder permissions MUST be
ensured by the responsible system administrator.

The plugin's files and folder SHOULD NOT be created, 
as root.

## Logging

The ILIAS log file is used whenever the plugin considers something
to be important to log.

## Dependencies

* CronElectronicCourseReserve Plugin (https://github.com/DatabayAG/CronElectronicCourseReserve)
* GnuPG (https://wiki.ubuntuusers.de/GnuPG/)

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

## License

See LICENSE file in this repository.