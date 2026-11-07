<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

// Baca dari ENV (Railway) atau default (XAMPP lokal)
$DB_HOST = getenv('DB_HOST') ?: 'localhost';
$DB_USER = getenv('DB_USER') ?: 'root';
$DB_PASS = getenv('DB_PASS') ?: '';
$DB_NAME = getenv('DB_NAME') ?: 'inventory_db';

$conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS);
if ($conn->connect_error) die("<h3>❌ DB error: " . $conn->connect_error . "</h3>");
$conn->query("CREATE DATABASE IF NOT EXISTS `$DB_NAME`");
$conn->select_db($DB_NAME);
$conn->set_charset("utf8mb4");

$conn->query("CREATE TABLE IF NOT EXISTS users(id INT AUTO_INCREMENT PRIMARY KEY,nama VARCHAR(100) NOT NULL,username VARCHAR(50) UNIQUE NOT NULL,password VARCHAR(255) NOT NULL,role ENUM('admin','staff') DEFAULT 'staff',created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
$conn->query("CREATE TABLE IF NOT EXISTS kategori(id INT AUTO_INCREMENT PRIMARY KEY,nama_kategori VARCHAR(100) NOT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
$conn->query("CREATE TABLE IF NOT EXISTS produk(id INT AUTO_INCREMENT PRIMARY KEY,kode_produk VARCHAR(50) UNIQUE NOT NULL,nama_produk VARCHAR(150) NOT NULL,kategori_id INT,satuan VARCHAR(20) DEFAULT 'pcs',stok INT DEFAULT 0,minimum_stock INT DEFAULT 5,harga_beli DECIMAL(15,2) DEFAULT 0,harga_jual DECIMAL(15,2) DEFAULT 0,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
$conn->query("CREATE TABLE IF NOT EXISTS transaksi_stok(id INT AUTO_INCREMENT PRIMARY KEY,produk_id INT NOT NULL,tipe ENUM('masuk','keluar','opname') NOT NULL,jumlah INT NOT NULL,stok_sebelum INT NOT NULL,stok_sesudah INT NOT NULL,keterangan TEXT,user_id INT,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");

if ($conn->query("SELECT COUNT(*) c FROM users")->fetch_assoc()['c'] == 0) {
    $p = password_hash('admin123', PASSWORD_DEFAULT);
    $conn->query("INSERT INTO users(nama,username,password,role) VALUES('Administrator','admin','$p','admin'),('Staff Gudang','staff','$p','staff')");
    $conn->query("INSERT INTO kategori(nama_kategori) VALUES('Elektronik'),('ATK'),('Makanan')");
    $conn->query("INSERT INTO produk(kode_produk,nama_produk,kategori_id,satuan,stok,minimum_stock,harga_beli,harga_jual) VALUES('PRD001','Mouse Logitech',1,'pcs',15,5,50000,75000),('PRD002','Keyboard Mechanical',1,'pcs',3,5,300000,450000),('PRD003','Pulpen Standard',2,'pcs',100,20,2000,3000)");
}

function isLogin(){return isset($_SESSION['user_id']);}
function isAdmin(){return ($_SESSION['role']??'')==='admin';}
function redirect($u){header("Location: $u");exit;}
function flash($m){$_SESSION['flash']=$m;}
function getFlash(){$f=$_SESSION['flash']??'';unset($_SESSION['flash']);return $f;}

$action = $_GET['action'] ?? 'dashboard';
$err = '';

if ($action==='login' && $_SERVER['REQUEST_METHOD']==='POST') {
    $u = $conn->real_escape_string($_POST['username']);
    $r = $conn->query("SELECT * FROM users WHERE username='$u'")->fetch_assoc();
    if ($r && password_verify($_POST['password'], $r['password'])) {
        $_SESSION['user_id']=$r['id'];$_SESSION['nama']=$r['nama'];$_SESSION['role']=$r['role'];
        redirect('?action=dashboard');
    }
    $err='Username atau password salah!';
}
if ($action==='logout'){session_destroy();redirect('?action=login');}

if (!isLogin()) {
    ?><!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Login Inventory</title>
    <style>*{box-sizing:border-box;margin:0;padding:0;font-family:-apple-system,'Segoe UI',sans-serif}body{display:flex;align-items:center;justify-content:center;min-height:100vh;background:linear-gradient(135deg,#667eea,#764ba2);padding:20px}.box{background:#fff;padding:35px;border-radius:16px;width:100%;max-width:360px;box-shadow:0 20px 50px rgba(0,0,0,.25)}h2{text-align:center;color:#1f2937;margin-bottom:6px;font-size:22px}.sub{text-align:center;color:#888;font-size:13px;margin-bottom:20px}input{width:100%;padding:14px;margin:8px 0;border:1px solid #ddd;border-radius:10px;font-size:16px}button{width:100%;padding:14px;margin-top:10px;background:#667eea;color:#fff;border:none;border-radius:10px;font-weight:600;cursor:pointer;font-size:16px}.err{color:#dc2626;text-align:center;font-size:13px;margin-bottom:8px}.info{text-align:center;font-size:12px;color:#888;margin-top:16px;line-height:1.6}</style></head><body>
    <div class="box"><h2>📦 Inventory System</h2><p class="sub">Silakan login</p>
    <?php if($err):?><p class="err"><?=htmlspecialchars($err)?></p><?php endif;?>
    <form method="POST" action="?action=login">
    <input name="username" placeholder="Username" required autofocus>
    <input type="password" name="password" placeholder="Password" required>
    <button>Login</button></form>
    <p class="info">Admin: admin / admin123<br>Staff: staff / admin123</p></div></body></html><?php
    exit;
}

if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['form'])) {
    $f = $_POST['form'];
    if ($f==='tambah_user' && isAdmin()) {
        $nama=$conn->real_escape_string($_POST['nama']);$u=$conn->real_escape_string($_POST['username']);
        $p=password_hash($_POST['password'],PASSWORD_DEFAULT);$r=$_POST['role'];
        if ($conn->query("INSERT INTO users(nama,username,password,role) VALUES('$nama','$u','$p','$r')")) flash('✅ User ditambahkan'); else flash('❌ '.$conn->error);
        redirect('?action=users');
    }
    if ($f==='tambah_kategori') {
        $n=$conn->real_escape_string($_POST['nama_kategori']);
        $conn->query("INSERT INTO kategori(nama_kategori) VALUES('$n')");
        flash('✅ Kategori ditambahkan');redirect('?action=kategori');
    }
    if ($f==='tambah_produk') {
        $kode=$conn->real_escape_string($_POST['kode']);$nama=$conn->real_escape_string($_POST['nama']);
        $kat=(int)$_POST['kategori_id'];$sat=$conn->real_escape_string($_POST['satuan']);
        $min=(int)$_POST['min'];$hb=(float)$_POST['hb'];$hj=(float)$_POST['hj'];
        $ks=$kat>0?$kat:"NULL";
        if ($conn->query("INSERT INTO produk(kode_produk,nama_produk,kategori_id,satuan,minimum_stock,harga_beli,harga_jual) VALUES('$kode','$nama',$ks,'$sat',$min,$hb,$hj)")) flash('✅ Produk ditambahkan'); else flash('❌ '.$conn->error);
        redirect('?action=produk');
    }
    if (in_array($f,['stok_masuk','stok_keluar','stok_opname'])) {
        $pid=(int)$_POST['produk_id'];$uid=$_SESSION['user_id'];
        $p=$conn->query("SELECT stok FROM produk WHERE id=$pid")->fetch_assoc();
        if (!$p){flash('❌ Produk tidak ditemukan');redirect("?action=$f");}
        $before=$p['stok'];
        if ($f==='stok_masuk'){$jml=(int)$_POST['jumlah'];$after=$before+$jml;$tipe='masuk';$ket=$conn->real_escape_string($_POST['keterangan']);}
        elseif ($f==='stok_keluar'){$jml=(int)$_POST['jumlah'];if($jml>$before){flash('❌ Stok tidak cukup! Tersedia: '.$before);redirect('?action=stok_keluar');}$after=$before-$jml;$tipe='keluar';$ket=$conn->real_escape_string($_POST['keterangan']);}
        else {$after=(int)$_POST['stok_fisik'];$jml=$after-$before;$tipe='opname';$ket='Opname: '.$conn->real_escape_string($_POST['keterangan']);}
        $conn->query("UPDATE produk SET stok=$after WHERE id=$pid");
        $conn->query("INSERT INTO transaksi_stok(produk_id,tipe,jumlah,stok_sebelum,stok_sesudah,keterangan,user_id) VALUES($pid,'$tipe',$jml,$before,$after,'$ket',$uid)");
        flash('✅ Transaksi tersimpan');redirect("?action=$f");
    }
}

if (isset($_GET['delete'])) {
    $d=$_GET['delete'];$id=(int)($_GET['id']??0);
    if ($d==='user'&&isAdmin()){$conn->query("DELETE FROM users WHERE id=$id AND role!='admin'");redirect('?action=users');}
    if ($d==='kategori'){$conn->query("DELETE FROM kategori WHERE id=$id");redirect('?action=kategori');}
    if ($d==='produk'){$conn->query("DELETE FROM produk WHERE id=$id");redirect('?action=produk');}
}

if ($action==='export_excel') {
    $jenis=$_GET['jenis']??'stok';$dari=$_GET['dari']??date('Y-m-01');$sampai=$_GET['sampai']??date('Y-m-d');
    header("Content-Type: application/vnd.ms-excel");
    header("Content-Disposition: attachment; filename=Laporan_$jenis.xls");
    echo "<meta charset='utf-8'><table border='1'>";
    if ($jenis==='stok') {
        echo "<tr><th>Kode</th><th>Nama</th><th>Kategori</th><th>Stok</th><th>Min</th><th>Status</th></tr>";
        $q=$conn->query("SELECT p.*,k.nama_kategori FROM produk p LEFT JOIN kategori k ON p.kategori_id=k.id ORDER BY p.nama_produk");
        while($r=$q->fetch_assoc()){$s=$r['stok']<=$r['minimum_stock']?'LOW':'OK';echo "<tr><td>{$r['kode_produk']}</td><td>{$r['nama_produk']}</td><td>{$r['nama_kategori']}</td><td>{$r['stok']}</td><td>{$r['minimum_stock']}</td><td>$s</td></tr>";}
    } else {
        echo "<tr><th>Tanggal</th><th>Produk</th><th>Jumlah</th><th>Sebelum</th><th>Sesudah</th><th>User</th><th>Ket</th></tr>";
        $q=$conn->query("SELECT t.*,p.nama_produk,u.nama FROM transaksi_stok t JOIN produk p ON t.produk_id=p.id LEFT JOIN users u ON t.user_id=u.id WHERE t.tipe='$jenis' AND DATE(t.created_at) BETWEEN '$dari' AND '$sampai' ORDER BY t.id DESC");
        while($r=$q->fetch_assoc()){echo "<tr><td>{$r['created_at']}</td><td>{$r['nama_produk']}</td><td>{$r['jumlah']}</td><td>{$r['stok_sebelum']}</td><td>{$r['stok_sesudah']}</td><td>{$r['nama']}</td><td>{$r['keterangan']}</td></tr>";}
    }
    echo "</table>";exit;
}

if ($action==='export_pdf') {
    $jenis=$_GET['jenis']??'stok';$dari=$_GET['dari']??date('Y-m-01');$sampai=$_GET['sampai']??date('Y-m-d');
    ?><!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Laporan</title>
    <style>body{font-family:-apple-system,Arial;font-size:12px;padding:15px}h2{text-align:center;margin-bottom:5px;font-size:16px}.sub{text-align:center;color:#666;margin-bottom:15px;font-size:11px}table{width:100%;border-collapse:collapse}th,td{border:1px solid #333;padding:6px;text-align:left;font-size:10px}th{background:#eee}.noprint{text-align:center;margin-bottom:20px}.noprint button{padding:12px 24px;background:#667eea;color:#fff;border:none;border-radius:8px;font-size:14px;font-weight:600}@media print{.noprint{display:none}}</style></head><body>
    <div class="noprint"><button onclick="window.print()">🖨️ Print / Save PDF</button></div>
    <h2>Laporan Inventory - <?=strtoupper($jenis)?></h2><p class="sub">Periode: <?=$dari?> s/d <?=$sampai?></p><table>
    <?php if($jenis==='stok'):?>
        <tr><th>Kode</th><th>Nama</th><th>Kategori</th><th>Stok</th><th>Min</th><th>Status</th></tr>
        <?php $q=$conn->query("SELECT p.*,k.nama_kategori FROM produk p LEFT JOIN kategori k ON p.kategori_id=k.id ORDER BY p.nama_produk");
        while($r=$q->fetch_assoc()):?>
            <tr><td><?=$r['kode_produk']?></td><td><?=$r['nama_produk']?></td><td><?=$r['nama_kategori']?></td><td><?=$r['stok']?></td><td><?=$r['minimum_stock']?></td><td><?=$r['stok']<=$r['minimum_stock']?'LOW':'OK'?></td></tr>
        <?php endwhile;?>
    <?php else:?>
        <tr><th>Tanggal</th><th>Produk</th><th>Jumlah</th><th>Sebelum</th><th>Sesudah</th><th>User</th></tr>
        <?php $q=$conn->query("SELECT t.*,p.nama_produk,u.nama FROM transaksi_stok t JOIN produk p ON t.produk_id=p.id LEFT JOIN users u ON t.user_id=u.id WHERE t.tipe='$jenis' AND DATE(t.created_at) BETWEEN '$dari' AND '$sampai' ORDER BY t.id DESC");
        while($r=$q->fetch_assoc()):?>
            <tr><td><?=$r['created_at']?></td><td><?=$r['nama_produk']?></td><td><?=$r['jumlah']?></td><td><?=$r['stok_sebelum']?></td><td><?=$r['stok_sesudah']?></td><td><?=$r['nama']?></td></tr>
        <?php endwhile;?>
    <?php endif;?>
    </table></body></html><?php exit;
}

$pageTitle='';ob_start();

if ($action==='dashboard') {
    $pageTitle='📊 Dashboard';
    $tp=$conn->query("SELECT COUNT(*) c FROM produk")->fetch_assoc()['c'];
    $ts=$conn->query("SELECT COALESCE(SUM(stok),0) s FROM produk")->fetch_assoc()['s'];
    $ms=$conn->query("SELECT COUNT(*) c FROM produk WHERE stok<=minimum_stock")->fetch_assoc()['c'];
    $th=$conn->query("SELECT COUNT(*) c FROM transaksi_stok WHERE DATE(created_at)=CURDATE()")->fetch_assoc()['c'];
    ?>
    <div class="cards">
    <div class="card"><h3>Total Produk</h3><p><?=$tp?></p></div>
    <div class="card"><h3>Total Stok</h3><p><?=$ts?></p></div>
    <div class="card warn"><h3>⚠️ Stok Min</h3><p><?=$ms?></p></div>
    <div class="card"><h3>Trx Hari Ini</h3><p><?=$th?></p></div>
    </div>
    <h2>⚠️ Minimum Stock Alert</h2>
    <div class="tbl-wrap"><table><tr><th>Kode</th><th>Nama</th><th>Stok</th><th>Status</th></tr>
    <?php $q=$conn->query("SELECT * FROM produk WHERE stok<=minimum_stock ORDER BY stok");
    if($q->num_rows==0)echo "<tr><td colspan='4' class='empty'>Semua stok aman ✅</td></tr>";
    while($r=$q->fetch_assoc()):?>
        <tr><td><?=$r['kode_produk']?></td><td><?=$r['nama_produk']?></td><td style="color:#dc2626;font-weight:bold"><?=$r['stok']?></td><td><span class="badge red">LOW</span></td></tr>
    <?php endwhile;?></table></div>
    <h2>🕒 Transaksi Terbaru</h2>
    <div class="tbl-wrap"><table><tr><th>Tgl</th><th>Produk</th><th>Tipe</th><th>Jml</th></tr>
    <?php $q=$conn->query("SELECT t.*,p.nama_produk FROM transaksi_stok t JOIN produk p ON t.produk_id=p.id ORDER BY t.id DESC LIMIT 10");
    while($r=$q->fetch_assoc()):?>
        <tr><td><?=date('d/m H:i',strtotime($r['created_at']))?></td><td><?=$r['nama_produk']?></td><td><span class="badge <?=$r['tipe']=='masuk'?'green':($r['tipe']=='keluar'?'red':'')?>"><?=strtoupper($r['tipe'])?></span></td><td><?=$r['jumlah']?></td></tr>
    <?php endwhile;?></table></div>
    <?php
}
elseif ($action==='users') {
    if(!isAdmin()){echo "<h2>⛔ Akses ditolak</h2>";}
    else{$pageTitle='👤 User';?>
    <form method="POST" class="form-grid"><input type="hidden" name="form" value="tambah_user">
    <input name="nama" placeholder="Nama" required><input name="username" placeholder="Username" required>
    <input type="password" name="password" placeholder="Password" required>
    <select name="role"><option value="staff">Staff</option><option value="admin">Admin</option></select>
    <button>+ Tambah</button></form>
    <div class="tbl-wrap"><table><tr><th>Nama</th><th>Username</th><th>Role</th><th>Aksi</th></tr>
    <?php $q=$conn->query("SELECT * FROM users ORDER BY id");
    while($r=$q->fetch_assoc()):?>
        <tr><td><?=$r['nama']?></td><td><?=$r['username']?></td><td><span class="badge <?=$r['role']=='admin'?'red':'green'?>"><?=$r['role']?></span></td>
        <td><?php if($r['role']!='admin'):?><a href="?action=users&delete=user&id=<?=$r['id']?>" onclick="return confirm('Hapus?')">Hapus</a><?php else:?>-<?php endif;?></td></tr>
    <?php endwhile;?></table></div>
    <?php }
}
elseif ($action==='kategori') {
    $pageTitle='🗂️ Kategori';?>
    <form method="POST" class="form-inline"><input type="hidden" name="form" value="tambah_kategori">
    <input name="nama_kategori" placeholder="Nama Kategori" required style="flex:1"><button>+ Tambah</button></form>
    <div class="tbl-wrap"><table><tr><th>ID</th><th>Nama</th><th>Produk</th><th>Aksi</th></tr>
    <?php $q=$conn->query("SELECT k.*,(SELECT COUNT(*) FROM produk WHERE kategori_id=k.id) jml FROM kategori k ORDER BY k.id");
    while($r=$q->fetch_assoc()):?>
        <tr><td><?=$r['id']?></td><td><?=$r['nama_kategori']?></td><td><?=$r['jml']?></td>
        <td><a href="?action=kategori&delete=kategori&id=<?=$r['id']?>" onclick="return confirm('Hapus?')">Hapus</a></td></tr>
    <?php endwhile;?></table></div>
    <?php
}
elseif ($action==='produk') {
    $pageTitle='📦 Produk';$cari=$conn->real_escape_string($_GET['cari']??'');
    $where=$cari?"WHERE p.nama_produk LIKE '%$cari%' OR p.kode_produk LIKE '%$cari%'":'';?>
    <form method="POST" class="form-grid"><input type="hidden" name="form" value="tambah_produk">
    <input name="kode" placeholder="Kode" required><input name="nama" placeholder="Nama Produk" required>
    <select name="kategori_id"><option value="0">-- Kategori --</option>
    <?php $k=$conn->query("SELECT * FROM kategori");while($r=$k->fetch_assoc()):?>
        <option value="<?=$r['id']?>"><?=$r['nama_kategori']?></option>
    <?php endwhile;?></select>
    <input name="satuan" placeholder="Satuan" value="pcs">
    <input type="number" name="min" placeholder="Min Stok" value="5">
    <input type="number" name="hb" placeholder="Harga Beli" value="0">
    <input type="number" name="hj" placeholder="Harga Jual" value="0">
    <button>+ Tambah Produk</button></form>
    <form method="GET" class="form-inline"><input type="hidden" name="action" value="produk">
    <input name="cari" placeholder="🔍 Cari..." value="<?=htmlspecialchars($cari)?>" style="flex:1"><button>Cari</button>
    <?php if($cari):?><a href="?action=produk" class="btn-sec">Reset</a><?php endif;?></form>
    <div class="tbl-wrap"><table><tr><th>Kode</th><th>Nama</th><th>Stok</th><th>Status</th><th>Aksi</th></tr>
    <?php $q=$conn->query("SELECT p.*,k.nama_kategori FROM produk p LEFT JOIN kategori k ON p.kategori_id=k.id $where ORDER BY p.id DESC");
    if($q->num_rows==0)echo "<tr><td colspan='5' class='empty'>Tidak ada produk</td></tr>";
    while($r=$q->fetch_assoc()):?>
        <tr><td><?=$r['kode_produk']?></td><td><?=$r['nama_produk']?></td><td><?=$r['stok']?></td>
        <td><?=$r['stok']<=$r['minimum_stock']?'<span class="badge red">LOW</span>':'<span class="badge green">OK</span>'?></td>
        <td><a href="?action=produk&delete=produk&id=<?=$r['id']?>" onclick="return confirm('Hapus?')">Hapus</a></td></tr>
    <?php endwhile;?></table></div>
    <?php
}
elseif (in_array($action,['stok_masuk','stok_keluar','stok_opname'])) {
    $labels=['stok_masuk'=>'⬇️ Stok Masuk','stok_keluar'=>'⬆️ Stok Keluar','stok_opname'=>'📋 Stok Opname'];
    $tipe=str_replace('stok_','',$action);$pageTitle=$labels[$action];?>
    <form method="POST" class="form-grid"><input type="hidden" name="form" value="<?=$action?>">
    <select name="produk_id" required style="grid-column:1/-1"><option value="">-- Pilih Produk --</option>
    <?php $q=$conn->query("SELECT * FROM produk ORDER BY nama_produk");while($r=$q->fetch_assoc()):?>
        <option value="<?=$r['id']?>"><?=$r['kode_produk']?> - <?=$r['nama_produk']?> (Stok: <?=$r['stok']?>)</option>
    <?php endwhile;?></select>
    <?php if($action==='stok_opname'):?><input type="number" name="stok_fisik" placeholder="Stok Fisik" min="0" required>
    <?php else:?><input type="number" name="jumlah" placeholder="Jumlah" min="1" required><?php endif;?>
    <input name="keterangan" placeholder="Keterangan"><button>💾 Simpan</button></form>
    <h2>Riwayat <?=ucfirst($tipe)?></h2>
    <div class="tbl-wrap"><table><tr><th>Tgl</th><th>Produk</th><th>Jml</th><th>User</th></tr>
    <?php $q=$conn->query("SELECT t.*,p.nama_produk,u.nama FROM transaksi_stok t JOIN produk p ON t.produk_id=p.id LEFT JOIN users u ON t.user_id=u.id WHERE t.tipe='$tipe' ORDER BY t.id DESC LIMIT 50");
    if($q->num_rows==0)echo "<tr><td colspan='4' class='empty'>Belum ada data</td></tr>";
    while($r=$q->fetch_assoc()):?>
        <tr><td><?=date('d/m H:i',strtotime($r['created_at']))?></td><td><?=$r['nama_produk']?></td>
        <td style="font-weight:bold;color:<?=$r['jumlah']>0?'#059669':($r['jumlah']<0?'#dc2626':'#666')?>"><?=$r['jumlah']>0?'+':''?><?=$r['jumlah']?></td>
        <td><?=$r['nama']??'-'?></td></tr>
    <?php endwhile;?></table></div>
    <?php
}
elseif ($action==='riwayat') {
    $pageTitle='🕒 Riwayat';$filter=$_GET['tipe']??'';$where=$filter?"WHERE t.tipe='$filter'":'';?>
    <form method="GET" class="form-inline"><input type="hidden" name="action" value="riwayat">
    <select name="tipe"><option value="">Semua</option>
    <option value="masuk" <?=$filter=='masuk'?'selected':''?>>Masuk</option>
    <option value="keluar" <?=$filter=='keluar'?'selected':''?>>Keluar</option>
    <option value="opname" <?=$filter=='opname'?'selected':''?>>Opname</option>
    </select><button>Filter</button></form>
    <div class="tbl-wrap"><table><tr><th>Tgl</th><th>Produk</th><th>Tipe</th><th>Jml</th><th>User</th></tr>
    <?php $q=$conn->query("SELECT t.*,p.nama_produk,u.nama FROM transaksi_stok t JOIN produk p ON t.produk_id=p.id LEFT JOIN users u ON t.user_id=u.id $where ORDER BY t.id DESC LIMIT 100");
    while($r=$q->fetch_assoc()):?>
        <tr><td><?=date('d/m H:i',strtotime($r['created_at']))?></td><td><?=$r['nama_produk']?></td>
        <td><span class="badge <?=$r['tipe']=='masuk'?'green':($r['tipe']=='keluar'?'red':'')?>"><?=strtoupper($r['tipe'])?></span></td>
        <td><?=$r['jumlah']?></td><td><?=$r['nama']??'-'?></td></tr>
    <?php endwhile;?></table></div>
    <?php
}
elseif ($action==='laporan') {
    $pageTitle='📊 Laporan';$jenis=$_GET['jenis']??'stok';$dari=$_GET['dari']??date('Y-m-01');$sampai=$_GET['sampai']??date('Y-m-d');?>
    <form method="GET" class="form-grid"><input type="hidden" name="action" value="laporan">
    <select name="jenis" style="grid-column:1/-1">
    <option value="stok" <?=$jenis=='stok'?'selected':''?>>Stok Real Time</option>
    <option value="masuk" <?=$jenis=='masuk'?'selected':''?>>Barang Masuk</option>
    <option value="keluar" <?=$jenis=='keluar'?'selected':''?>>Barang Keluar</option>
    </select>
    <input type="date" name="dari" value="<?=$dari?>"><input type="date" name="sampai" value="<?=$sampai?>">
    <
