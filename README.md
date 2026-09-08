# SippEnkripsi

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D7.4-blue.svg)](https://php.net)

Library PHP **standalone** untuk enkripsi dan dekripsi `perkara_id` aplikasi **SIPP (Sistem Informasi Penelusuran Perkara)** yang sepenuhnya **backward-compatible** dengan aplikasi SIPP lama yang berjalan di **PHP 5 & CodeIgniter 2 (mcrypt)**.

Library ini dapat digunakan di aplikasi modern berbasis **PHP 7.4, 8.0, 8.1, 8.2, 8.3+** (seperti Laravel, Symfony, Slim, CodeIgniter 4, atau script PHP native) **tanpa memerlukan CodeIgniter 2 atau ekstensi PHP `mcrypt`** yang sudah dihapus sejak PHP 7.2.

---

## Fitur Utama

- **100% Kompatibel dengan SIPP PHP 5 (CodeIgniter 2 Encrypt):** Menggunakan algoritma yang sama persis (Rijndael-256 CBC, key 256-bit MD5, random IV, cipher noise permutation, zero padding).
- **Standalone:** Tidak bergantung pada framework CodeIgniter (`CI_Controller`, `get_instance()`, `BASEPATH`, dll).
- **Menggunakan phpseclib3:** Murni PHP, tidak memerlukan kompilasi C ekstensi `mcrypt`.
- **URL-Safe Encoding:** Menyediakan helper `encodeUrlSafe()` dan `decodeUrlSafe()` untuk kemudahan pengiriman `perkara_id` melalui parameter URL atau URI segment tanpa resiko karakter `+`, `/`, `=` terpotong/rusak.
- **Dukungan PSR-4 Autoloading:** Siap digunakan via Composer dan dipublikasikan ke Packagist.
- **Backward-Compatible Alias:** Mendukung pemanggilan method gaya lama (`get_key`, `set_key`, `mcrypt_encode`, `mcrypt_decode`, dan class `Legacyen`).

---

## Persyaratan Sistem

- PHP `>= 7.4` atau `>= 8.0`
- Ekstensi PHP standar: `openssl`, `mbstring`, `hash` (biasanya sudah aktif di PHP)
- Composer

---

## Instalasi

Instal via Composer:

```bash
composer require itpajaku/sippenkripsi
```

---

## Cara Penggunaan

### 1. Inisialisasi & Penggunaan Dasar

Kunci enkripsi (`encryption_key`) harus sama persis dengan yang ada di konfigurasi SIPP Anda (`application/config/config.php` di SIPP: `$config['encryption_key']`).

```php
<?php

require_once 'vendor/autoload.php';

use SippEnkripsi\SippEnkripsi;

// Kunci enkripsi dari config SIPP
$key = 'kunci_rahasia_sipp_anda';

$sipp = new SippEnkripsi($key);

// Enkripsi perkara_id
$perkaraId = '12345';
$encrypted = $sipp->encode($perkaraId);
echo "Encrypted: " . $encrypted . PHP_EOL;
// Contoh output: 5qN8E4QrhkEoL7T2cbpmzG1Fj/X7aV4S6V2FwxZc5+JbKZ6yEtBEjbNFbKBYBfr/eypAAOG/+dQHnc9wABeVMQ==

// Dekripsi perkara_id yang berasal dari SIPP
$decrypted = $sipp->decode($encrypted);
echo "Decrypted: " . $decrypted . PHP_EOL;
// Output: 12345
```

---

### 2. URL-Safe Enkripsi & Dekripsi

Jika Anda ingin mengirimkan `perkara_id` terenkripsi melalui parameter URL atau segment rute tanpa khawatir karakter `/`, `+`, atau `=` rusak oleh URL encoder web server:

```php
// Enkripsi URL-safe (aman untuk URL)
$urlEncrypted = $sipp->encodeUrlSafe('12345');
echo $urlEncrypted;
// Output tanpa karakter '+', '/', atau '='

// Dekripsi kembali dari parameter URL
$id = $sipp->decodeUrlSafe($urlEncrypted);
echo $id; // 12345
```

---

### 3. Mengatur Kunci Dinamis

Anda dapat mengatur atau mengganti kunci kapan saja:

```php
$sipp = new SippEnkripsi();

// Mengatur kunci via setKey()
$sipp->setKey('kunci_sipp_pengadilan_a');
$encryptedA = $sipp->encode('1001');

// Atau oper kunci langsung pada method encode/decode
$encryptedB = $sipp->encode('1001', 'kunci_sipp_pengadilan_b');
$decryptedB = $sipp->decode($encryptedB, 'kunci_sipp_pengadilan_b');
```

---

### 4. Alias dan Kompatibilitas Legacy

Jika Anda memiliki kode eksisting yang menggunakan penamaan CodeIgniter atau class `Legacyen`:

```php
// Alias method
$sipp->encrypt($data); // sama dengan encode()
$sipp->decrypt($data); // sama dengan decode()
$sipp->set_key($key);  // sama dengan setKey()
$sipp->get_key();      // sama dengan getKey()

// Direct include tanpa composer (opsional)
require_once 'SippEnkripsi.php';
$legacy = new Legacyen('kunci_rahasia');
$enc = $legacy->encode('12345');
```

---

## Menjalankan Unit Test

Library ini dilengkapi dengan unit test berbasis PHPUnit:

```bash
composer test
# atau
./vendor/bin/phpunit
```

---

## Self-Host REST API dengan Docker (FrankenPHP)

Selain digunakan sebagai library PHP, project ini juga dapat di-deploy sebagai **REST API Microservice** yang cepat dan ringan menggunakan **Docker Compose & FrankenPHP (PHP 8.3)**.

Ini sangat berguna jika aplikasi Anda ditulis menggunakan bahasa lain (Python, Node.js, Go, Rust, C#, Java, dll) namun perlu melakukan enkripsi / dekripsi `perkara_id` yang kompatibel dengan SIPP.

### 1. Menjalankan Server

1. Salin file environment:
   ```bash
   cp .env.example .env
   ```
2. Sesuaikan konfigurasi di `.env`:
   ```env
   PORT=8080
   SIPP_ENCRYPTION_KEY=kunci_rahasia_sipp_anda
   API_KEY=opsional_token_rahasia
   ```
3. Jalankan container dengan Docker Compose:
   ```bash
   docker compose up -d
   ```
   API akan berjalan di `http://localhost:8080`.

---

### 2. Dokumentasi Endpoint REST API

#### A. Health Check
- **Endpoint**: `GET /health`
- **Contoh Request**:
  ```bash
  curl http://localhost:8080/health
  ```
- **Response**:
  ```json
  {
    "status": "ok",
    "service": "SippEnkripsi REST API",
    "version": "1.0.0",
    "php_version": "8.3.x",
    "has_default_key": true,
    "timestamp": 1788855546
  }
  ```

#### B. Enkripsi (Encrypt)
- **Endpoint**: `POST /encrypt`
- **Headers**:
  - `Content-Type: application/json`
  - `X-API-Key: <token>` *(Wajib jika `API_KEY` diisi di .env)*
- **Body JSON**:
  ```json
  {
    "data": "12345",
    "url_safe": false,
    "key": "opsional_custom_key"
  }
  ```
- **Contoh Request (cURL)**:
  ```bash
  curl -X POST http://localhost:8080/encrypt \
    -H "Content-Type: application/json" \
    -d '{"data": "12345"}'
  ```
- **Response**:
  ```json
  {
    "success": true,
    "result": "5qN8E4QrhkEoL7T2cbpmzG1Fj/X7aV4S6V2FwxZc5+JbKZ6yEtBEjbNFbKBYBfr/eypAAOG/+dQHnc9wABeVMQ==",
    "url_safe": false
  }
  ```

#### C. Dekripsi (Decrypt)
- **Endpoint**: `POST /decrypt`
- **Headers**:
  - `Content-Type: application/json`
  - `X-API-Key: <token>` *(Wajib jika `API_KEY` diisi di .env)*
- **Body JSON**:
  ```json
  {
    "data": "5qN8E4QrhkEoL7T2cbpmzG1Fj/X7aV4S6V2FwxZc5+JbKZ6yEtBEjbNFbKBYBfr/eypAAOG/+dQHnc9wABeVMQ==",
    "url_safe": false,
    "key": "opsional_custom_key"
  }
  ```
- **Contoh Request (cURL)**:
  ```bash
  curl -X POST http://localhost:8080/decrypt \
    -H "Content-Type: application/json" \
    -d '{"data": "5qN8E4QrhkEoL7T2cbpmzG1Fj/X7aV4S6V2FwxZc5+JbKZ6yEtBEjbNFbKBYBfr/eypAAOG/+dQHnc9wABeVMQ=="}'
  ```
- **Response**:
  ```json
  {
    "success": true,
    "result": "12345",
    "url_safe": false
  }
  ```

---

## Lisensi

[MIT License](LICENSE) &copy; 2026 SippEnkripsi Contributors.
