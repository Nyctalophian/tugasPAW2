<?php
/**
 * upload.php
 * ─────────────────────────────────────────────────────────────────
 * ALUR (tanpa JavaScript):
 *
 * LANGKAH 1 — POST dari index.html
 *   → Validasi semua input & file di PHP
 *   → Jika valid: simpan file ke /uploads/tmp/ (sementara)
 *   → Tampilkan halaman PREVIEW + form konfirmasi
 *
 * LANGKAH 2 — User klik "Konfirmasi & Simpan" di halaman preview
 *   → PHP pindahkan file dari /uploads/tmp/ ke /uploads/
 *   → Simpan log
 *   → Tampilkan halaman SUKSES
 *
 * ─────────────────────────────────────────────────────────────────
 */

/* ══════════════════════════════
   KONFIGURASI
══════════════════════════════ */
define('UPLOAD_DIR',     __DIR__ . '/uploads/');
define('TMP_DIR',        __DIR__ . '/uploads/tmp/');
define('MAX_FILES',      5);
define('MAX_SIZE_MB',    5);
define('MAX_SIZE',       MAX_SIZE_MB * 1024 * 1024);
define('ALLOWED_EXT',    ['jpg', 'jpeg', 'png']);
define('ALLOWED_MIME',   ['image/jpeg', 'image/jpg', 'image/png']);

/* ── Buat folder jika belum ada ── */
if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);
if (!is_dir(TMP_DIR))    mkdir(TMP_DIR,    0755, true);

/* ══════════════════════════════
   TENTUKAN LANGKAH MANA YANG AKTIF
   $_POST['langkah'] dikirim dari form tersembunyi
══════════════════════════════ */
$langkah = $_POST['langkah'] ?? '1';

/* ════════════════════════════════════════════════════
   LANGKAH 1: Terima upload dari index.html
════════════════════════════════════════════════════ */
if ($langkah === '1') {

    /* ── Sanitasi input form ── */
    $nama_barang       = htmlspecialchars(trim($_POST['nama_barang']       ?? ''));
    $bukti_kepemilikan = htmlspecialchars(trim($_POST['bukti_kepemilikan'] ?? ''));
    $ciri_khusus       = htmlspecialchars(trim($_POST['ciri_khusus']       ?? ''));
    $nomor_telepon     = htmlspecialchars(trim($_POST['nomor_telepon']      ?? ''));
    $email             = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
    $kategori          = htmlspecialchars(trim($_POST['kategori']           ?? ''));

    $errors = [];

    /* ── Validasi field wajib ── */
    if (empty($nama_barang))       $errors[] = 'Nama barang wajib diisi.';
    if (empty($bukti_kepemilikan)) $errors[] = 'Bukti kepemilikan wajib diisi.';
    if (empty($nomor_telepon))     $errors[] = 'Nomor telepon wajib diisi.';
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL))
                                   $errors[] = 'Format email tidak valid.';
    if (empty($kategori))          $errors[] = 'Kategori barang wajib dipilih.';

    /* ── Validasi & simpan file ke TMP ── */
    $tmp_saved = []; // file yang berhasil disimpan sementara

    if (!isset($_FILES['foto_barang']) || empty($_FILES['foto_barang']['name'][0])) {
        $errors[] = 'Minimal 1 foto harus diupload.';
    } else {
        $files      = $_FILES['foto_barang'];
        $file_count = count($files['name']);

        if ($file_count > MAX_FILES) {
            $errors[] = 'Maksimal ' . MAX_FILES . ' foto yang dapat diupload.';
        } else {
            for ($i = 0; $i < $file_count; $i++) {

                if ($files['error'][$i] !== UPLOAD_ERR_OK) {
                    $errors[] = 'Error upload file ke-' . ($i + 1) . '.';
                    continue;
                }

                $original   = $files['name'][$i];
                $tmp_path   = $files['tmp_name'][$i];
                $size       = $files['size'][$i];

                /* ── Operasi string: ekstrak & bersihkan ekstensi ── */
                $ext        = strtolower(pathinfo($original, PATHINFO_EXTENSION));
                $base_name  = pathinfo($original, PATHINFO_FILENAME);
                $clean_base = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $base_name);
                $clean_base = strtolower(substr($clean_base, 0, 40));

                /* ── Validasi ekstensi ── */
                if (!in_array($ext, ALLOWED_EXT)) {
                    $errors[] = "\"$original\": ekstensi tidak diizinkan. Hanya JPG dan PNG.";
                    continue;
                }

                /* ── Validasi MIME type (cek isi file, bukan hanya nama) ── */
                $mime = mime_content_type($tmp_path);
                if (!in_array($mime, ALLOWED_MIME)) {
                    $errors[] = "\"$original\": bukan file gambar yang valid.";
                    continue;
                }

                /* ── Validasi ukuran ── */
                if ($size > MAX_SIZE) {
                    $mb = round($size / 1024 / 1024, 1);
                    $errors[] = "\"$original\" ($mb MB) melebihi batas " . MAX_SIZE_MB . " MB.";
                    continue;
                }

                /* ── Simpan ke TMP dengan nama unik ── */
                $tmp_name = 'tmp_' . date('YmdHis') . '_' . $i . '_' . $clean_base . '.' . $ext;
                $tmp_dest = TMP_DIR . $tmp_name;

                if (move_uploaded_file($tmp_path, $tmp_dest)) {
                    $tmp_saved[] = [
                        'tmp_name' => $tmp_name,       // nama file di folder tmp
                        'original' => $original,        // nama asli dari user
                        'ext'      => $ext,
                        'mime'     => $mime,
                        'size_kb'  => round($size / 1024, 1),
                    ];
                } else {
                    $errors[] = "Gagal menyimpan \"$original\". Periksa izin folder.";
                }
            }
        }
    }

    /* ── Jika ada error: kembali ke form dengan pesan ── */
    if (!empty($errors)) {
        tampil_form_dengan_error($errors, [
            'nama_barang'       => $nama_barang,
            'bukti_kepemilikan' => $bukti_kepemilikan,
            'ciri_khusus'       => $ciri_khusus,
            'nomor_telepon'     => $nomor_telepon,
            'email'             => $email,
            'kategori'          => $kategori,
        ]);
        exit;
    }

    /* ── Tidak ada error: tampilkan preview ── */
    tampil_preview($nama_barang, $bukti_kepemilikan, $ciri_khusus,
                   $nomor_telepon, $email, $kategori, $tmp_saved);
    exit;
}

/* ════════════════════════════════════════════════════
   LANGKAH 2: Konfirmasi — pindahkan dari TMP ke final
════════════════════════════════════════════════════ */
if ($langkah === '2') {

    /* ── Ambil data dari hidden input ── */
    $nama_barang       = htmlspecialchars(trim($_POST['nama_barang']       ?? ''));
    $bukti_kepemilikan = htmlspecialchars(trim($_POST['bukti_kepemilikan'] ?? ''));
    $ciri_khusus       = htmlspecialchars(trim($_POST['ciri_khusus']       ?? ''));
    $nomor_telepon     = htmlspecialchars(trim($_POST['nomor_telepon']      ?? ''));
    $email             = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
    $kategori          = htmlspecialchars(trim($_POST['kategori']           ?? ''));
    $tmp_names         = $_POST['tmp_names'] ?? []; // array nama file tmp

    $errors   = [];
    $final    = [];
    $clean_kat = preg_replace('/[^a-z0-9]/', '', strtolower($kategori));

    foreach ($tmp_names as $tmp_name) {

        /* Sanitasi nama file tmp (cegah path traversal) */
        $tmp_name = basename($tmp_name);
        $src      = TMP_DIR . $tmp_name;

        if (!file_exists($src)) {
            $errors[] = "File sementara \"$tmp_name\" tidak ditemukan. Silakan upload ulang.";
            continue;
        }

        /* ── Operasi string: buat nama file final ── */
        $ext          = strtolower(pathinfo($tmp_name, PATHINFO_EXTENSION));
        $rand         = substr(bin2hex(random_bytes(3)), 0, 6);
        $final_name   = date('Ymd_His') . '_' . $clean_kat . '_' . $rand . '.' . $ext;
        $dest         = UPLOAD_DIR . $final_name;

        if (rename($src, $dest)) {
            $final[] = [
                'final_name' => $final_name,
                'tmp_name'   => $tmp_name,
                'ext'        => strtoupper($ext),
            ];
        } else {
            $errors[] = "Gagal memindahkan file \"$tmp_name\".";
        }
    }

    /* ── Simpan log ── */
    if (!empty($final)) {
        $log = implode(' | ', [
            date('Y-m-d H:i:s'),
            "Barang: $nama_barang",
            "Kategori: $kategori",
            "Telepon: $nomor_telepon",
            "Email: $email",
            "Foto: " . count($final),
        ]) . PHP_EOL;
        file_put_contents(UPLOAD_DIR . 'laporan_log.txt', $log, FILE_APPEND | LOCK_EX);
    }

    tampil_sukses($nama_barang, $kategori, $nomor_telepon, $email, $final, $errors);
    exit;
}


/* ════════════════════════════════════════════════════
   FUNGSI: TAMPIL FORM DENGAN ERROR (kembali ke atas)
════════════════════════════════════════════════════ */
function tampil_form_dengan_error(array $errors, array $data) {
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Form Laporan Barang Temuan</title>
  <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;700&family=Inter:wght@400;600;700&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="style.css"/>
</head>
<body>
<header class="topbar">
  <div class="topbar-menu"><span></span><span></span><span></span></div>
  <span class="topbar-title">Daftar Barang Temuan</span>
  <span class="topbar-icon">
    <svg width="24" height="24" fill="none" viewBox="0 0 24 24"
         stroke="#FAFCF8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
      <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
    </svg>
  </span>
</header>

<div class="page-wrapper">
  <div class="card">
    <div class="card-header">
      <h2>Form Laporan Barang Temuan</h2>
      <p>Isi informasi barang yang Anda temukan</p>
    </div>

    <!-- Tampilkan error -->
    <div class="alert alert-danger" style="display:block">
      <strong>❌ Terdapat kesalahan, silakan perbaiki:</strong>
      <ul>
        <?php foreach ($errors as $e): ?>
          <li><?= $e ?></li>
        <?php endforeach; ?>
      </ul>
    </div>

    <form action="upload.php" method="POST" enctype="multipart/form-data">
      <input type="hidden" name="langkah" value="1" />
      <div class="form-body">

        <div class="field">
          <label for="nama_barang">Nama Barang <span class="req">*</span></label>
          <input type="text" id="nama_barang" name="nama_barang"
                 placeholder="Contoh: Dompet Kulit"
                 value="<?= htmlspecialchars($data['nama_barang']) ?>" required/>
        </div>

        <div class="field">
          <label for="nomor_telepon">Nomor Telepon <span class="req">*</span></label>
          <input type="tel" id="nomor_telepon" name="nomor_telepon"
                 placeholder="Contoh: 08265217621"
                 value="<?= htmlspecialchars($data['nomor_telepon']) ?>" required/>
        </div>

        <div class="field">
          <label for="email">Email <span class="req">*</span></label>
          <input type="email" id="email" name="email"
                 placeholder="Contoh: nama@email.com"
                 value="<?= htmlspecialchars($data['email']) ?>" required/>
        </div>

        <div class="field">
          <label for="kategori">Kategori Barang <span class="req">*</span></label>
          <div class="select-wrap">
            <select id="kategori" name="kategori" required>
              <option value="" disabled <?= empty($data['kategori']) ? 'selected' : '' ?>>Pilih kategori...</option>
              <?php
              $opts = ['elektronik'=>'Elektronik','dompet_tas'=>'Dompet / Tas',
                       'dokumen'=>'Dokumen / Kartu','aksesoris'=>'Aksesoris / Perhiasan',
                       'pakaian'=>'Pakaian','kendaraan'=>'Kunci / Kendaraan','lainnya'=>'Lainnya'];
              foreach ($opts as $val => $label):
              ?>
                <option value="<?= $val ?>" <?= $data['kategori'] === $val ? 'selected' : '' ?>>
                  <?= $label ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="field full">
          <label for="bukti_kepemilikan">Bukti Kepemilikan <span class="req">*</span></label>
          <textarea id="bukti_kepemilikan" name="bukti_kepemilikan" required
                    placeholder="Jelaskan bagaimana Anda bisa membuktikan kepemilikan barang ini..."
          ><?= htmlspecialchars($data['bukti_kepemilikan']) ?></textarea>
        </div>

        <div class="field full">
          <label for="ciri_khusus">Ciri-ciri Khusus</label>
          <input type="text" id="ciri_khusus" name="ciri_khusus"
                 placeholder="Sebutkan ciri-ciri khusus dari barang"
                 value="<?= htmlspecialchars($data['ciri_khusus']) ?>"/>
        </div>

        <div class="field full">
          <label for="foto_barang">Foto Barang <span class="req">*</span></label>
          <p class="field-hint">
            Hanya <strong>.jpg</strong> dan <strong>.png</strong> — Maks <strong>5 foto</strong>, masing-masing maks 5 MB.
          </p>
          <input type="file" id="foto_barang" name="foto_barang[]"
                 accept=".jpg,.jpeg,.png" multiple/>
        </div>

      </div>
      <div class="form-footer">
        <button type="submit" class="btn-submit">
          Ajukan Klaim
          <svg width="16" height="16" fill="none" viewBox="0 0 16 16">
            <path d="M2 8h10m-4-4l4 4-4 4" stroke="#FAFCF8" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </button>
        <a href="index.html" class="btn-secondary">Batal</a>
      </div>
    </form>
  </div>
</div>
</body>
</html>
<?php
}


/* ════════════════════════════════════════════════════
   FUNGSI: TAMPIL PREVIEW (langkah 1 sukses)
════════════════════════════════════════════════════ */
function tampil_preview($nama, $bukti, $ciri, $telepon, $email, $kategori, array $files) {
    $opts_label = ['elektronik'=>'Elektronik','dompet_tas'=>'Dompet / Tas',
                   'dokumen'=>'Dokumen / Kartu','aksesoris'=>'Aksesoris / Perhiasan',
                   'pakaian'=>'Pakaian','kendaraan'=>'Kunci / Kendaraan','lainnya'=>'Lainnya'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Preview Laporan — Konfirmasi</title>
  <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;700&family=Inter:wght@400;600;700&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="style.css"/>
</head>
<body>
<header class="topbar">
  <div class="topbar-menu"><span></span><span></span><span></span></div>
  <span class="topbar-title">Daftar Barang Temuan</span>
  <span class="topbar-icon">
    <svg width="24" height="24" fill="none" viewBox="0 0 24 24"
         stroke="#FAFCF8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
      <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
    </svg>
  </span>
</header>

<div class="page-wrapper">
  <div class="card">

    <div class="card-header">
      <h2>Preview Laporan — Periksa sebelum konfirmasi</h2>
      <p>Pastikan data dan foto sudah benar, lalu klik Konfirmasi &amp; Simpan</p>
    </div>

    <div class="alert alert-success" style="display:block">
      ✅ File berhasil diupload. Periksa data di bawah lalu klik <strong>Konfirmasi &amp; Simpan</strong>.
    </div>

    <!-- DATA RINGKASAN -->
    <div style="padding: 20px 20px 0;">
      <div class="result-section">
        <div class="result-title">Data Laporan</div>
        <div class="result-row">
          <span class="result-key">Nama Barang</span>
          <span class="result-val"><?= $nama ?></span>
        </div>
        <div class="result-row">
          <span class="result-key">Kategori</span>
          <span class="result-val"><?= $opts_label[$kategori] ?? $kategori ?></span>
        </div>
        <div class="result-row">
          <span class="result-key">Ciri-ciri Khusus</span>
          <span class="result-val"><?= $ciri ?: '–' ?></span>
        </div>
        <div class="result-row">
          <span class="result-key">Nomor Telepon</span>
          <span class="result-val"><?= $telepon ?></span>
        </div>
        <div class="result-row">
          <span class="result-key">Email</span>
          <span class="result-val"><?= $email ?></span>
        </div>
      </div>
    </div>

    <!-- PREVIEW FOTO — ditampilkan dari folder tmp/ -->
    <?php if (!empty($files)): ?>
    <div style="padding: 0 20px 20px;">
      <div class="result-title">Preview Foto (<?= count($files) ?>)</div>
      <div class="preview-grid">
        <?php foreach ($files as $f): ?>
          <div class="preview-item">
            <!--
              Foto ditampilkan dari folder uploads/tmp/
              PHP membaca file yang sudah tersimpan, lalu browser menampilkannya.
              Ini adalah cara preview tanpa JavaScript.
            -->
            <img src="uploads/tmp/<?= urlencode($f['tmp_name']) ?>"
                 alt="<?= htmlspecialchars($f['original']) ?>" />
            <div class="fname">
              <?php
                /* Operasi string: potong nama asli agar tidak terlalu panjang */
                $base  = pathinfo($f['original'], PATHINFO_FILENAME);
                $label = (strlen($base) > 12 ? substr($base, 0, 12) . '…' : $base) . '.' . $f['ext'];
                echo htmlspecialchars($label);
              ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- FORM KONFIRMASI (langkah 2) -->
    <!--
      Semua data dikirim ulang lewat hidden input.
      Tidak ada JS — hanya form HTML biasa yang submit ke PHP.
    -->
    <form action="upload.php" method="POST">
      <input type="hidden" name="langkah"          value="2" />
      <input type="hidden" name="nama_barang"       value="<?= htmlspecialchars($nama) ?>" />
      <input type="hidden" name="bukti_kepemilikan" value="<?= htmlspecialchars($bukti) ?>" />
      <input type="hidden" name="ciri_khusus"       value="<?= htmlspecialchars($ciri) ?>" />
      <input type="hidden" name="nomor_telepon"     value="<?= htmlspecialchars($telepon) ?>" />
      <input type="hidden" name="email"             value="<?= htmlspecialchars($email) ?>" />
      <input type="hidden" name="kategori"          value="<?= htmlspecialchars($kategori) ?>" />

      <!-- Kirim semua nama file tmp sebagai array -->
      <?php foreach ($files as $f): ?>
        <input type="hidden" name="tmp_names[]" value="<?= htmlspecialchars($f['tmp_name']) ?>" />
      <?php endforeach; ?>

      <div class="form-footer">
        <button type="submit" class="btn-submit">
          Konfirmasi &amp; Simpan
          <svg width="16" height="16" fill="none" viewBox="0 0 16 16">
            <path d="M2 8h10m-4-4l4 4-4 4" stroke="#FAFCF8" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </button>
        <a href="index.html" class="btn-danger">Batal &amp; Ulangi</a>
      </div>
    </form>

  </div>
</div>
</body>
</html>
<?php
}


/* ════════════════════════════════════════════════════
   FUNGSI: TAMPIL SUKSES (langkah 2 selesai)
════════════════════════════════════════════════════ */
function tampil_sukses($nama, $kategori, $telepon, $email, array $final, array $errors) {
    $opts_label = ['elektronik'=>'Elektronik','dompet_tas'=>'Dompet / Tas',
                   'dokumen'=>'Dokumen / Kartu','aksesoris'=>'Aksesoris / Perhiasan',
                   'pakaian'=>'Pakaian','kendaraan'=>'Kunci / Kendaraan','lainnya'=>'Lainnya'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Laporan Berhasil Disimpan</title>
  <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;700&family=Inter:wght@400;600;700&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="style.css"/>
</head>
<body>
<header class="topbar">
  <div class="topbar-menu"><span></span><span></span><span></span></div>
  <span class="topbar-title">Daftar Barang Temuan</span>
  <span class="topbar-icon">
    <svg width="24" height="24" fill="none" viewBox="0 0 24 24"
         stroke="#FAFCF8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
      <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
    </svg>
  </span>
</header>

<div class="page-wrapper">
  <div class="card">

    <div class="card-header">
      <h2>Laporan Berhasil Disimpan</h2>
      <p>Terima kasih, laporan Anda telah kami terima</p>
    </div>

    <div style="padding: 20px; display: flex; flex-direction: column; gap: 20px;">

      <?php if (!empty($errors)): ?>
        <div class="alert alert-danger" style="display:block">
          <strong>Beberapa file gagal disimpan:</strong>
          <ul><?php foreach ($errors as $e): ?><li><?= $e ?></li><?php endforeach; ?></ul>
        </div>
      <?php endif; ?>

      <div class="alert alert-success" style="display:block">
        ✅ <strong>Laporan berhasil disimpan!</strong>
        Kami akan menghubungi Anda melalui <strong><?= $telepon ?></strong>
        atau <strong><?= $email ?></strong>.
      </div>

      <!-- Ringkasan data -->
      <div class="result-section">
        <div class="result-title">Ringkasan Laporan</div>
        <div class="result-row">
          <span class="result-key">Nama Barang</span>
          <span class="result-val"><?= $nama ?></span>
        </div>
        <div class="result-row">
          <span class="result-key">Kategori</span>
          <span class="result-val"><?= $opts_label[$kategori] ?? $kategori ?></span>
        </div>
        <div class="result-row">
          <span class="result-key">Telepon</span>
          <span class="result-val"><?= $telepon ?></span>
        </div>
        <div class="result-row">
          <span class="result-key">Email</span>
          <span class="result-val"><?= $email ?></span>
        </div>
        <div class="result-row">
          <span class="result-key">Waktu Pengajuan</span>
          <span class="result-val"><?= date('d M Y, H:i') ?> WIB</span>
        </div>
      </div>

      <!-- File yang tersimpan -->
      <?php if (!empty($final)): ?>
        <div class="result-section">
          <div class="result-title">File Tersimpan (<?= count($final) ?>)</div>
          <div class="file-list">
            <?php foreach ($final as $f): ?>
              <div class="file-item">
                <div class="file-info">
                  <div class="file-name"><?= htmlspecialchars($f['final_name']) ?></div>
                </div>
                <span class="ext-badge"><?= $f['ext'] ?></span>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>

      <a href="index.html" class="btn-submit" style="text-align:center; justify-content:center;">
        ← Ajukan Laporan Baru
      </a>

    </div>
  </div>
</div>
</body>
</html>
<?php
}
?>