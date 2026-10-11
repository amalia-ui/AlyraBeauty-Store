<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/escape.php';
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/validation.php';

$page = (string)($_GET['page'] ?? (current_user() ? 'dashboard' : 'login'));
$notice = '';
$error = '';
if (isset($_SESSION['flash'])) { $notice = (string)$_SESSION['flash']; unset($_SESSION['flash']); }

function redirect_to(string $page): never {
    header('Location: index.php?page=' . rawurlencode($page));
    exit;
}
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function verify_csrf(): void {
    if (!hash_equals($_SESSION['csrf'] ?? '', (string)($_POST['csrf'] ?? ''))) {
        throw new RuntimeException('Sesi formulir tidak valid. Muat ulang halaman dan coba lagi.');
    }
}
function post_int(string $key): int {
    $v = filter_input(INPUT_POST, $key, FILTER_VALIDATE_INT);
    if ($v === false || $v === null || $v < 1) throw new InvalidArgumentException('Data pilihan tidak valid.');
    return $v;
}
function layout_start(string $title, string $active): void {
    $user = current_user();
    echo '<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>'.e($title).' | AlyraBeauty</title><link rel="stylesheet" href="assets/style.css"></head><body><div class="layout"><aside class="sidebar"><div class="brand">Alyra<span>Beauty</span></div><nav class="nav"><div class="nav-label">Menu utama</div>';
    $links = ['dashboard'=>'Dashboard','products'=>'Produk','categories'=>'Kategori','suppliers'=>'Supplier','movements'=>'Transaksi Stok','reports'=>'Laporan'];
    foreach ($links as $key=>$label) echo '<a class="'.($active===$key?'active':'').'" href="index.php?page='.$key.'">'.e($label).'</a>';
    if (is_admin()) echo '<div class="nav-label">Administrasi</div><a class="'.($active==='users'?'active':'').'" href="index.php?page=users">Pengguna</a>';
    echo '</nav></aside><main class="main"><header class="topbar"><div><strong>'.e($title).'</strong><br><small>Sistem Manajemen Persediaan</small></div><div><strong>'.e($user['full_name'] ?? '').'</strong><br><small>'.e(strtoupper($user['role'] ?? '')).' · <a href="index.php?page=logout">Keluar</a></small></div></header><section class="content">';
    if (!empty($GLOBALS['notice'])) echo '<div class="alert">'.e($GLOBALS['notice']).'</div>';
    if (!empty($GLOBALS['error'])) echo '<div class="alert error">'.e($GLOBALS['error']).'</div>';
}
function layout_end(): void { echo '</section></main></div></body></html>'; }
function page_title(string $title, string $subtitle, string $button=''): void {
    echo '<div class="pagehead"><div><h1>'.e($title).'</h1><div class="muted">'.e($subtitle).'</div></div>'.$button.'</div>';
}
function require_admin_page(): void { require_admin(); }
function count_query(PDO $pdo, string $sql, array $params=[]): int {
    $s=$pdo->prepare($sql);$s->execute($params);return (int)$s->fetchColumn();
}

if ($page === 'logout') {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time()-42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy(); redirect_to('login');
}

if ($page === 'login') {
    if (current_user()) redirect_to('dashboard');
    if ($_SERVER['REQUEST_METHOD']==='POST') {
        try {
            verify_csrf();
            $username = trim((string)($_POST['username'] ?? ''));
            $password = (string)($_POST['password'] ?? '');
            $stmt=$pdo->prepare('SELECT id,username,password_hash,full_name,role FROM users WHERE username=:username LIMIT 1');
            $stmt->execute(['username'=>$username]); $user=$stmt->fetch();
            if ($user && password_verify($password, $user['password_hash'])) {
                session_regenerate_id(true);
                $_SESSION['user']=['id'=>(int)$user['id'],'username'=>$user['username'],'full_name'=>$user['full_name'],'role'=>$user['role']];
                $_SESSION['csrf']=bin2hex(random_bytes(32));
                redirect_to('dashboard');
            }
            $error='Username atau password salah.';
        } catch (Throwable $ex) { $error=$ex->getMessage(); }
    }
    ?><!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Login | AlyraBeauty</title><link rel="stylesheet" href="assets/style.css"></head><body><div class="login-wrap"><form class="login-card" method="post"><div class="brand">Alyra<span>Beauty</span></div><h1>Selamat datang</h1><p class="muted">Masuk untuk mengelola inventory toko.</p><?php if($error): ?><div class="alert error"><?=e($error)?></div><?php endif; ?><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><div class="field"><label>Username</label><input name="username" required maxlength="60" autocomplete="username"></div><div class="field"><label>Password</label><input type="password" name="password" required autocomplete="current-password"></div><button class="btn" style="width:100%" type="submit">Masuk</button><p class="footnote">Demo awal: admin / password</p></form></div></body></html><?php
    exit;
}
require_login();

try {
    if ($_SERVER['REQUEST_METHOD']==='POST') {
        verify_csrf();
        switch ($page) {
            case 'categories':
                require_admin();
                $action=$_POST['action'] ?? '';
                if ($action==='save') {
                    $id=(int)($_POST['id'] ?? 0); $name=post_string('name',100); $desc=post_string('description',255);
                    if ($name==='') throw new InvalidArgumentException('Nama kategori wajib diisi.');
                    if ($id) { $s=$pdo->prepare('UPDATE categories SET name=:name,description=:description WHERE id=:id'); $s->execute(['name'=>$name,'description'=>$desc?:null,'id'=>$id]); }
                    else { $s=$pdo->prepare('INSERT INTO categories(name,description) VALUES(:name,:description)'); $s->execute(['name'=>$name,'description'=>$desc?:null]); }
                    $_SESSION['flash']='Kategori berhasil disimpan.'; redirect_to('categories');
                } elseif ($action==='delete') {
                    $s=$pdo->prepare('DELETE FROM categories WHERE id=:id');$s->execute(['id'=>post_int('id')]);
                    $_SESSION['flash']='Kategori berhasil dihapus.';redirect_to('categories');
                }
                break;
            case 'products':
                require_admin();
                $action=$_POST['action'] ?? '';
                if ($action==='save') {
                    $id=(int)($_POST['id'] ?? 0); $category=post_int('category_id'); $sku=post_string('sku',50); $name=post_string('name',150);
                    $price=nonnegative_number('price'); $minimum=nonnegative_number('minimum_stock');
                    if ($sku===''||$name==='') throw new InvalidArgumentException('SKU dan nama produk wajib diisi.');
                    if ($id) {
                        $s=$pdo->prepare('UPDATE products SET category_id=:cat,sku=:sku,name=:name,price=:price,minimum_stock=:min WHERE id=:id');
                        $s->execute(['cat'=>$category,'sku'=>$sku,'name'=>$name,'price'=>$price,'min'=>(int)$minimum,'id'=>$id]);
                    } else {
                        $s=$pdo->prepare('INSERT INTO products(category_id,sku,name,price,stock,minimum_stock) VALUES(:cat,:sku,:name,:price,0,:min)');
                        $s->execute(['cat'=>$category,'sku'=>$sku,'name'=>$name,'price'=>$price,'min'=>(int)$minimum]);
                    }
                    $_SESSION['flash']='Produk berhasil disimpan. Perubahan stok dilakukan melalui menu Transaksi Stok.';redirect_to('products');
                } elseif ($action==='delete') {
                    $s=$pdo->prepare('DELETE FROM products WHERE id=:id');$s->execute(['id'=>post_int('id')]);
                    $_SESSION['flash']='Produk berhasil dihapus.';redirect_to('products');
                }
                break;
            case 'suppliers':
                require_admin();
                $action=$_POST['action'] ?? '';
                if ($action==='save') {
                    $id=(int)($_POST['id'] ?? 0);$name=post_string('name',150);$phone=post_string('phone',30);$email=post_string('email',120);$address=post_string('address',255);
                    if($name==='')throw new InvalidArgumentException('Nama supplier wajib diisi.');
                    if($email!==''&&!filter_var($email,FILTER_VALIDATE_EMAIL))throw new InvalidArgumentException('Format email tidak valid.');
                    if($id){$s=$pdo->prepare('UPDATE suppliers SET name=:name,phone=:phone,email=:email,address=:address WHERE id=:id');$s->execute(['name'=>$name,'phone'=>$phone?:null,'email'=>$email?:null,'address'=>$address?:null,'id'=>$id]);}
                    else{$s=$pdo->prepare('INSERT INTO suppliers(name,phone,email,address) VALUES(:name,:phone,:email,:address)');$s->execute(['name'=>$name,'phone'=>$phone?:null,'email'=>$email?:null,'address'=>$address?:null]);}
                    $_SESSION['flash']='Supplier berhasil disimpan.';redirect_to('suppliers');
                } elseif($action==='delete'){$s=$pdo->prepare('DELETE FROM suppliers WHERE id=:id');$s->execute(['id'=>post_int('id')]);$_SESSION['flash']='Supplier berhasil dihapus.';redirect_to('suppliers');}
                elseif($action==='link') {
                    $pid=post_int('product_id');$sid=post_int('supplier_id');$price=nonnegative_number('supplier_price');
                    $s=$pdo->prepare('INSERT INTO product_suppliers(product_id,supplier_id,supplier_price) VALUES(:p,:s,:price) ON DUPLICATE KEY UPDATE supplier_price=VALUES(supplier_price)');
                    $s->execute(['p'=>$pid,'s'=>$sid,'price'=>$price]);$_SESSION['flash']='Relasi produk-supplier berhasil disimpan.';redirect_to('suppliers');
                }
                break;
            case 'movements':
                $productId=post_int('product_id');$qty=positive_int('quantity');$type=(string)($_POST['type'] ?? '');$note=post_string('note',255);
                if(!in_array($type,['IN','OUT'],true))throw new InvalidArgumentException('Jenis transaksi tidak valid.');
                $pdo->beginTransaction();
                try {
                    $s=$pdo->prepare('SELECT stock FROM products WHERE id=:id FOR UPDATE');$s->execute(['id'=>$productId]);$product=$s->fetch();
                    if(!$product)throw new InvalidArgumentException('Produk tidak ditemukan.');
                    if($type==='OUT'&&(int)$product['stock']<$qty)throw new InvalidArgumentException('Stok tidak cukup. Barang keluar tidak boleh melebihi stok tersedia.');
                    $delta=$type==='IN'?$qty:-$qty;
                    $s=$pdo->prepare('UPDATE products SET stock=stock+:delta WHERE id=:id');
                    $s->execute(['delta'=>$delta,'id'=>$productId]);
                    $s=$pdo->prepare('INSERT INTO stock_movements(product_id,user_id,type,quantity,note) VALUES(:p,:u,:t,:q,:n)');
                    $s->execute(['p'=>$productId,'u'=>current_user()['id'],'t'=>$type,'q'=>$qty,'n'=>$note?:null]);
                    $pdo->commit();
                } catch(Throwable $ex) { if($pdo->inTransaction())$pdo->rollBack(); throw $ex; }
                $_SESSION['flash']='Transaksi tersimpan dan stok berhasil diperbarui.';redirect_to('movements');
            case 'users':
                require_admin();
                $action=$_POST['action'] ?? '';
                if($action==='save'){
                    $id=(int)($_POST['id']??0);$username=post_string('username',60);$name=post_string('full_name',100);$role=(string)($_POST['role']??'staff');$password=(string)($_POST['password']??'');
                    if($username===''||$name==='')throw new InvalidArgumentException('Username dan nama wajib diisi.');
                    if(!in_array($role,['admin','staff'],true))throw new InvalidArgumentException('Role tidak valid.');
                    if($id){
                        if($password!==''){$s=$pdo->prepare('UPDATE users SET username=:u,full_name=:n,role=:r,password_hash=:p WHERE id=:id');$s->execute(['u'=>$username,'n'=>$name,'r'=>$role,'p'=>password_hash($password,PASSWORD_DEFAULT),'id'=>$id]);}
                        else{$s=$pdo->prepare('UPDATE users SET username=:u,full_name=:n,role=:r WHERE id=:id');$s->execute(['u'=>$username,'n'=>$name,'r'=>$role,'id'=>$id]);}
                    }else{
                        if(strlen($password)<8)throw new InvalidArgumentException('Password pengguna baru minimal 8 karakter.');
                        $s=$pdo->prepare('INSERT INTO users(username,full_name,role,password_hash) VALUES(:u,:n,:r,:p)');$s->execute(['u'=>$username,'n'=>$name,'r'=>$role,'p'=>password_hash($password,PASSWORD_DEFAULT)]);
                    }
                    $_SESSION['flash']='Data pengguna berhasil disimpan.';redirect_to('users');
                }elseif($action==='delete'){
                    $id=post_int('id');if($id===(int)current_user()['id'])throw new InvalidArgumentException('Akun yang sedang digunakan tidak boleh dihapus.');
                    $s=$pdo->prepare('DELETE FROM users WHERE id=:id');$s->execute(['id'=>$id]);$_SESSION['flash']='Pengguna berhasil dihapus.';redirect_to('users');
                }
                break;
        }
    }
} catch (Throwable $ex) {
    $error = $ex instanceof PDOException && str_contains($ex->getMessage(),'1062') ? 'Data duplikat. Pastikan SKU, username, atau nama kategori belum digunakan.' :
        ($ex instanceof PDOException && str_contains($ex->getMessage(),'1451') ? 'Data tidak dapat dihapus karena masih digunakan oleh data lain atau memiliki histori transaksi.' : $ex->getMessage());
}

switch ($page) {
case 'dashboard':
    layout_start('Dashboard','dashboard');
    $products=count_query($pdo,'SELECT COUNT(*) FROM products');
    $categories=count_query($pdo,'SELECT COUNT(*) FROM categories');
    $low=count_query($pdo,'SELECT COUNT(*) FROM products WHERE stock<=minimum_stock');
    $in=count_query($pdo,"SELECT COALESCE(SUM(quantity),0) FROM stock_movements WHERE type='IN'");
    $out=count_query($pdo,"SELECT COALESCE(SUM(quantity),0) FROM stock_movements WHERE type='OUT'");
    $value=(float)$pdo->query('SELECT COALESCE(SUM(stock*price),0) FROM products')->fetchColumn();
    page_title('Dashboard','Ringkasan persediaan dan aktivitas toko.');
    echo '<div class="grid">';
    foreach([['Total Produk',$products,'Produk terdaftar'],['Nilai Inventory',rupiah($value),'Total stok × harga jual'],['Produk Stok Rendah',$low,'Stok ≤ batas minimum'],['Kategori',$categories,'Kategori aktif']] as $c) echo '<div class="stat"><div class="label">'.e($c[0]).'</div><div class="value">'.e($c[1]).'</div><div class="hint">'.e($c[2]).'</div></div>';
    echo '</div><div class="grid"><div class="stat"><div class="label">Total Barang Masuk</div><div class="value">'.e($in).'</div><div class="hint">Akumulasi unit masuk</div></div><div class="stat"><div class="label">Total Barang Keluar</div><div class="value">'.e($out).'</div><div class="hint">Akumulasi unit keluar</div></div></div>';
    echo '<div class="panel"><h2>Produk dengan stok rendah</h2><div class="table-wrap"><table><thead><tr><th>SKU</th><th>Nama produk</th><th>Kategori</th><th>Stok</th><th>Minimum</th></tr></thead><tbody>';
    $s=$pdo->query('SELECT p.sku,p.name,c.name category_name,p.stock,p.minimum_stock FROM products p JOIN categories c ON c.id=p.category_id WHERE p.stock<=p.minimum_stock ORDER BY p.stock ASC LIMIT 10');
    foreach($s as $r)echo '<tr><td>'.e($r['sku']).'</td><td>'.e($r['name']).'</td><td>'.e($r['category_name']).'</td><td><span class="badge low">'.e($r['stock']).'</span></td><td>'.e($r['minimum_stock']).'</td></tr>';
    if(!$s->rowCount())echo '<tr><td colspan="5" class="empty">Semua produk memiliki stok di atas batas minimum.</td></tr>';
    echo '</tbody></table></div></div>';
    echo '<div class="panel"><h2>Transaksi terbaru</h2><div class="table-wrap"><table><thead><tr><th>Waktu</th><th>Produk</th><th>Jenis</th><th>Jumlah</th><th>Pengguna</th></tr></thead><tbody>';
    $s=$pdo->query("SELECT sm.created_at,p.name,sm.type,sm.quantity,u.full_name FROM stock_movements sm JOIN products p ON p.id=sm.product_id JOIN users u ON u.id=sm.user_id ORDER BY sm.id DESC LIMIT 8");
    foreach($s as $r)echo '<tr><td>'.e($r['created_at']).'</td><td>'.e($r['name']).'</td><td><span class="badge '.strtolower($r['type']).'">'.e($r['type']).'</span></td><td>'.e($r['quantity']).'</td><td>'.e($r['full_name']).'</td></tr>';
    if(!$s->rowCount())echo '<tr><td colspan="5" class="empty">Belum ada transaksi.</td></tr>';
    echo '</tbody></table></div></div>';layout_end();break;

case 'products':
    layout_start('Produk','products');
    $edit=null;
    if(isset($_GET['edit'])){$s=$pdo->prepare('SELECT * FROM products WHERE id=:id');$s->execute(['id'=>(int)$_GET['edit']]);$edit=$s->fetch();}
    $categories=$pdo->query('SELECT id,name FROM categories ORDER BY name')->fetchAll();
    page_title('Produk','Kelola katalog, harga, SKU, dan batas stok.',is_admin()?'<a class="btn" href="#product-form">+ Tambah Produk</a>':'');
    $search=trim((string)($_GET['q']??''));$cat=(int)($_GET['category_id']??0);
    $sort=(string)($_GET['sort']??'name');$dir=strtoupper((string)($_GET['dir']??'ASC'));
    $allowed=['name'=>'p.name','sku'=>'p.sku','price'=>'p.price','stock'=>'p.stock'];
    if(!isset($allowed[$sort]))$sort='name';if(!in_array($dir,['ASC','DESC'],true))$dir='ASC';
    $where=[];$params=[];if($search!==''){$where[]='(p.name LIKE :q OR p.sku LIKE :q)';$params['q']='%'.$search.'%';}
    if($cat>0){$where[]='p.category_id=:cat';$params['cat']=$cat;}
    $condition=$where?' WHERE '.implode(' AND ',$where):'';
    $perPage=10;$total=count_query($pdo,'SELECT COUNT(*) FROM products p'.$condition,$params);$totalPages=max(1,(int)ceil($total/$perPage));$pg=max(1,(int)($_GET['p']??1));$pg=min($pg,$totalPages);$offset=($pg-1)*$perPage;
    $sql='SELECT p.*,c.name category_name FROM products p JOIN categories c ON c.id=p.category_id'.$condition.' ORDER BY '.$allowed[$sort].' '.$dir.' LIMIT '.$perPage.' OFFSET '.$offset;
    $s=$pdo->prepare($sql);$s->execute($params);$rows=$s->fetchAll();
    echo '<div class="panel"><form class="toolbar" method="get"><input type="hidden" name="page" value="products"><input name="q" placeholder="Cari nama atau SKU..." value="'.e($search).'"><select name="category_id"><option value="0">Semua kategori</option>';
    foreach($categories as $c)echo '<option value="'.e($c['id']).'"'.($cat===$c['id']?' selected':'').'>'.e($c['name']).'</option>';
    echo '</select><select name="sort"><option value="name">Urut nama</option><option value="price"'.($sort==='price'?' selected':'').'>Urut harga</option><option value="stock"'.($sort==='stock'?' selected':'').'>Urut stok</option><option value="sku"'.($sort==='sku'?' selected':'').'>Urut SKU</option></select><select name="dir"><option>ASC</option><option'.($dir==='DESC'?' selected':'').'>DESC</option></select><button class="btn">Terapkan</button></form><div class="table-wrap"><table><thead><tr><th>SKU</th><th>Produk</th><th>Kategori</th><th>Harga</th><th>Stok</th><th>Min. stok</th>'.(is_admin()?'<th>Aksi</th>':'').'</tr></thead><tbody>';
    foreach($rows as $r){echo '<tr><td>'.e($r['sku']).'</td><td>'.e($r['name']).'</td><td>'.e($r['category_name']).'</td><td>'.e(rupiah($r['price'])).'</td><td>'.($r['stock']<=$r['minimum_stock']?'<span class="badge low">':'<span class="badge">').e($r['stock']).'</span></td><td>'.e($r['minimum_stock']).'</td>';
    if(is_admin())echo '<td><div class="actions"><a class="btn secondary" href="index.php?page=products&edit='.e($r['id']).'">Edit</a><form class="inline-form" method="post" onsubmit="return confirm(\'Hapus produk ini?\')"><input type="hidden" name="csrf" value="'.e(csrf_token()).'"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="'.e($r['id']).'"><button class="btn danger">Hapus</button></form></div></td>';
    echo '</tr>';}
    if(!$rows)echo '<tr><td colspan="7" class="empty">Produk tidak ditemukan.</td></tr>';
    echo '</tbody></table></div><div class="pagination"><span>Halaman '.$pg.' dari '.$totalPages.' · '.$total.' produk</span>';
    for($i=1;$i<=$totalPages;$i++){if($i===$pg)echo '<span class="current">'.$i.'</span>';else echo '<a href="index.php?page=products&q='.urlencode($search).'&category_id='.$cat.'&sort='.$sort.'&dir='.$dir.'&p='.$i.'">'.$i.'</a>';}
    echo '</div></div>';
    if(is_admin()) {
      echo '<div class="form-card" id="product-form"><h2>'.($edit?'Edit Produk':'Tambah Produk').'</h2><form method="post"><input type="hidden" name="csrf" value="'.e(csrf_token()).'"><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="'.e($edit['id']??0).'"><div class="form-grid"><div class="field"><label>SKU unik</label><input name="sku" maxlength="50" required value="'.e($edit['sku']??'').'"></div><div class="field"><label>Nama produk</label><input name="name" maxlength="150" required value="'.e($edit['name']??'').'"></div><div class="field"><label>Kategori</label><select name="category_id" required><option value="">Pilih kategori</option>';
      foreach($categories as $c)echo '<option value="'.e($c['id']).'"'.((int)($edit['category_id']??0)===(int)$c['id']?' selected':'').'>'.e($c['name']).'</option>';
      echo '</select></div><div class="field"><label>Harga (Rp)</label><input type="number" min="0" step="0.01" name="price" required value="'.e($edit['price']??0).'"></div><div class="field"><label>Minimum stok</label><input type="number" min="0" name="minimum_stock" required value="'.e($edit['minimum_stock']??5).'"></div></div><p class="muted">Stok awal diatur melalui transaksi Barang Masuk agar histori tercatat.</p><button class="btn">Simpan Produk</button> <a class="btn ghost" href="index.php?page=products">Batal</a></form></div>';
    }
    layout_end();break;

case 'categories':
    require_admin();layout_start('Kategori','categories');$edit=null;if(isset($_GET['edit'])){$s=$pdo->prepare('SELECT * FROM categories WHERE id=:id');$s->execute(['id'=>(int)$_GET['edit']]);$edit=$s->fetch();}
    page_title('Kategori','Mengelompokkan produk dalam tabel relasional.');
    echo '<div class="panel"><div class="table-wrap"><table><thead><tr><th>Nama kategori</th><th>Deskripsi</th><th>Jumlah produk</th><th>Aksi</th></tr></thead><tbody>';
    $rows=$pdo->query('SELECT c.*,COUNT(p.id) total_products FROM categories c LEFT JOIN products p ON p.category_id=c.id GROUP BY c.id ORDER BY c.name')->fetchAll();
    foreach($rows as $r)echo '<tr><td>'.e($r['name']).'</td><td>'.e($r['description']).'</td><td>'.e($r['total_products']).'</td><td><div class="actions"><a class="btn secondary" href="index.php?page=categories&edit='.e($r['id']).'">Edit</a><form class="inline-form" method="post" onsubmit="return confirm(\'Hapus kategori?\')"><input type="hidden" name="csrf" value="'.e(csrf_token()).'"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="'.e($r['id']).'"><button class="btn danger">Hapus</button></form></div></td></tr>';
    if(!$rows)echo '<tr><td colspan="4" class="empty">Belum ada kategori.</td></tr>';echo '</tbody></table></div></div>';
    echo '<div class="form-card"><h2>'.($edit?'Edit kategori':'Tambah kategori').'</h2><form method="post"><input type="hidden" name="csrf" value="'.e(csrf_token()).'"><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="'.e($edit['id']??0).'"><div class="field"><label>Nama kategori</label><input name="name" maxlength="100" required value="'.e($edit['name']??'').'"></div><div class="field"><label>Deskripsi</label><textarea name="description" maxlength="255">'.e($edit['description']??'').'</textarea></div><button class="btn">Simpan</button></form></div>';layout_end();break;

case 'suppliers':
    require_admin();layout_start('Supplier','suppliers');$edit=null;if(isset($_GET['edit'])){$s=$pdo->prepare('SELECT * FROM suppliers WHERE id=:id');$s->execute(['id'=>(int)$_GET['edit']]);$edit=$s->fetch();}
    page_title('Supplier','Data pemasok dan relasi many-to-many produk-supplier.');
    echo '<div class="panel"><h2>Daftar supplier</h2><div class="table-wrap"><table><thead><tr><th>Nama</th><th>Telepon</th><th>Email</th><th>Alamat</th><th>Aksi</th></tr></thead><tbody>';
    foreach($pdo->query('SELECT * FROM suppliers ORDER BY name') as $r)echo '<tr><td>'.e($r['name']).'</td><td>'.e($r['phone']).'</td><td>'.e($r['email']).'</td><td>'.e($r['address']).'</td><td><div class="actions"><a class="btn secondary" href="index.php?page=suppliers&edit='.e($r['id']).'">Edit</a><form class="inline-form" method="post" onsubmit="return confirm(\'Hapus supplier?\')"><input type="hidden" name="csrf" value="'.e(csrf_token()).'"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="'.e($r['id']).'"><button class="btn danger">Hapus</button></form></div></td></tr>';
    echo '</tbody></table></div></div>';
    echo '<div class="form-card"><h2>'.($edit?'Edit supplier':'Tambah supplier').'</h2><form method="post"><input type="hidden" name="csrf" value="'.e(csrf_token()).'"><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="'.e($edit['id']??0).'"><div class="form-grid">';
    foreach(['name'=>'Nama supplier','phone'=>'Telepon','email'=>'Email','address'=>'Alamat'] as $key=>$label)echo '<div class="field"><label>'.e($label).'</label><input name="'.e($key).'" maxlength="'.($key==='name'?150:($key==='address'?255:120)).'" '.($key==='name'?'required':'').' value="'.e($edit[$key]??'').'"></div>';
    echo '</div><button class="btn">Simpan Supplier</button></form></div>';
    echo '<div class="form-card"><h2>Hubungkan produk dengan supplier</h2><p class="muted">Satu produk dapat memiliki beberapa supplier, dan satu supplier dapat memasok beberapa produk.</p><form method="post"><input type="hidden" name="csrf" value="'.e(csrf_token()).'"><input type="hidden" name="action" value="link"><div class="form-grid"><div class="field"><label>Produk</label><select name="product_id" required>';
    foreach($pdo->query('SELECT id,sku,name FROM products ORDER BY name') as $p)echo '<option value="'.e($p['id']).'">'.e($p['sku'].' — '.$p['name']).'</option>';
    echo '</select></div><div class="field"><label>Supplier</label><select name="supplier_id" required>';
    foreach($pdo->query('SELECT id,name FROM suppliers ORDER BY name') as $p)echo '<option value="'.e($p['id']).'">'.e($p['name']).'</option>';
    echo '</select></div><div class="field"><label>Harga dari supplier (Rp)</label><input type="number" min="0" step="0.01" name="supplier_price" value="0" required></div></div><button class="btn">Simpan Relasi</button></form></div>';
    echo '<div class="panel"><h2>Relasi produk-supplier</h2><div class="table-wrap"><table><thead><tr><th>SKU</th><th>Produk</th><th>Supplier</th><th>Harga supplier</th></tr></thead><tbody>';
    $links=$pdo->query('SELECT p.sku,p.name,s.name supplier_name,ps.supplier_price FROM product_suppliers ps JOIN products p ON p.id=ps.product_id JOIN suppliers s ON s.id=ps.supplier_id ORDER BY p.name')->fetchAll();
    foreach($links as $r)echo '<tr><td>'.e($r['sku']).'</td><td>'.e($r['name']).'</td><td>'.e($r['supplier_name']).'</td><td>'.e(rupiah($r['supplier_price'])).'</td></tr>';
    if(!$links)echo '<tr><td colspan="4" class="empty">Belum ada relasi.</td></tr>';echo '</tbody></table></div></div>';layout_end();break;

case 'movements':
    layout_start('Transaksi Stok','movements');
    page_title('Transaksi Stok','Catat barang masuk dan keluar. Setiap transaksi tersimpan di histori.');
    echo '<div class="form-card"><h2>Catat transaksi</h2><form method="post"><input type="hidden" name="csrf" value="'.e(csrf_token()).'"><div class="form-grid"><div class="field"><label>Produk</label><select name="product_id" required>';
    foreach($pdo->query('SELECT id,sku,name,stock FROM products ORDER BY name') as $p)echo '<option value="'.e($p['id']).'">'.e($p['sku'].' — '.$p['name'].' (stok: '.$p['stock'].')').'</option>';
    echo '</select></div><div class="field"><label>Jenis transaksi</label><select name="type"><option value="IN">Barang Masuk</option><option value="OUT">Barang Keluar</option></select></div><div class="field"><label>Jumlah</label><input type="number" name="quantity" min="1" step="1" required></div><div class="field"><label>Catatan</label><input name="note" maxlength="255" placeholder="Contoh: pembelian / penjualan"></div></div><button class="btn">Simpan Transaksi</button></form></div>';
    echo '<div class="panel"><h2>Histori transaksi</h2><div class="table-wrap"><table><thead><tr><th>ID</th><th>Waktu</th><th>SKU</th><th>Produk</th><th>Jenis</th><th>Jumlah</th><th>Catatan</th><th>Pengguna</th></tr></thead><tbody>';
    $rows=$pdo->query('SELECT sm.*,p.sku,p.name product_name,u.full_name FROM stock_movements sm JOIN products p ON p.id=sm.product_id JOIN users u ON u.id=sm.user_id ORDER BY sm.id DESC LIMIT 100')->fetchAll();
    foreach($rows as $r)echo '<tr><td>'.e($r['id']).'</td><td>'.e($r['created_at']).'</td><td>'.e($r['sku']).'</td><td>'.e($r['product_name']).'</td><td><span class="badge '.strtolower($r['type']).'">'.e($r['type']).'</span></td><td>'.e($r['quantity']).'</td><td>'.e($r['note']).'</td><td>'.e($r['full_name']).'</td></tr>';
    if(!$rows)echo '<tr><td colspan="8" class="empty">Belum ada histori transaksi.</td></tr>';echo '</tbody></table></div></div>';layout_end();break;

case 'reports':
    layout_start('Laporan Inventory','reports');page_title('Laporan Inventory','Ringkasan berbasis JOIN, GROUP BY, COUNT, SUM, dan AVG.');
    echo '<div class="panel"><h2>Ringkasan per kategori</h2><div class="table-wrap"><table><thead><tr><th>Kategori</th><th>Total produk</th><th>Total stok</th><th>Rata-rata harga</th><th>Nilai inventory</th></tr></thead><tbody>';
    $rows=$pdo->query('SELECT c.name,COUNT(p.id) total_products,COALESCE(SUM(p.stock),0) total_stock,COALESCE(AVG(p.price),0) average_price,COALESCE(SUM(p.stock*p.price),0) inventory_value FROM categories c LEFT JOIN products p ON p.category_id=c.id GROUP BY c.id,c.name ORDER BY c.name')->fetchAll();
    foreach($rows as $r)echo '<tr><td>'.e($r['name']).'</td><td>'.e($r['total_products']).'</td><td>'.e($r['total_stock']).'</td><td>'.e(rupiah($r['average_price'])).'</td><td>'.e(rupiah($r['inventory_value'])).'</td></tr>';
    echo '</tbody></table></div></div><div class="panel"><h2>Barang masuk dan keluar per produk</h2><div class="table-wrap"><table><thead><tr><th>SKU</th><th>Produk</th><th>Total masuk</th><th>Total keluar</th><th>Stok saat ini</th><th>Status</th></tr></thead><tbody>';
    $rows=$pdo->query("SELECT p.id,p.sku,p.name,p.stock,p.minimum_stock,COALESCE(SUM(CASE WHEN sm.type='IN' THEN sm.quantity ELSE 0 END),0) total_in,COALESCE(SUM(CASE WHEN sm.type='OUT' THEN sm.quantity ELSE 0 END),0) total_out FROM products p LEFT JOIN stock_movements sm ON sm.product_id=p.id GROUP BY p.id,p.sku,p.name,p.stock,p.minimum_stock ORDER BY p.name")->fetchAll();
    foreach($rows as $r)echo '<tr><td>'.e($r['sku']).'</td><td>'.e($r['name']).'</td><td>'.e($r['total_in']).'</td><td>'.e($r['total_out']).'</td><td>'.e($r['stock']).'</td><td>'.($r['stock']<=$r['minimum_stock']?'<span class="badge low">Stok rendah</span>':'<span class="badge in">Aman</span>').'</td></tr>';
    echo '</tbody></table></div></div>';layout_end();break;

case 'users':
    require_admin();layout_start('Pengguna','users');$edit=null;if(isset($_GET['edit'])){$s=$pdo->prepare('SELECT id,username,full_name,role FROM users WHERE id=:id');$s->execute(['id'=>(int)$_GET['edit']]);$edit=$s->fetch();}
    page_title('Pengguna','Kelola akun Admin dan Staff.');
    echo '<div class="panel"><div class="table-wrap"><table><thead><tr><th>Nama</th><th>Username</th><th>Role</th><th>Aksi</th></tr></thead><tbody>';
    foreach($pdo->query('SELECT id,username,full_name,role FROM users ORDER BY full_name') as $r)echo '<tr><td>'.e($r['full_name']).'</td><td>'.e($r['username']).'</td><td><span class="badge">'.e(strtoupper($r['role'])).'</span></td><td><div class="actions"><a class="btn secondary" href="index.php?page=users&edit='.e($r['id']).'">Edit</a>'.((int)$r['id']!==(int)current_user()['id']?'<form class="inline-form" method="post" onsubmit="return confirm(\'Hapus akun ini?\')"><input type="hidden" name="csrf" value="'.e(csrf_token()).'"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="'.e($r['id']).'"><button class="btn danger">Hapus</button></form>':'').'</div></td></tr>';
    echo '</tbody></table></div></div>';
    echo '<div class="form-card"><h2>'.($edit?'Edit pengguna':'Tambah pengguna').'</h2><form method="post"><input type="hidden" name="csrf" value="'.e(csrf_token()).'"><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="'.e($edit['id']??0).'"><div class="form-grid"><div class="field"><label>Nama lengkap</label><input name="full_name" maxlength="100" required value="'.e($edit['full_name']??'').'"></div><div class="field"><label>Username</label><input name="username" maxlength="60" required value="'.e($edit['username']??'').'"></div><div class="field"><label>Role</label><select name="role"><option value="staff"'.(($edit['role']??'staff')==='staff'?' selected':'').'>Staff</option><option value="admin"'.(($edit['role']??'')==='admin'?' selected':'').'>Admin</option></select></div><div class="field"><label>Password '.($edit?'(kosongkan jika tidak diganti)':'(minimal 8 karakter)').'</label><input type="password" name="password" '.($edit?'':'required minlength="8"').' autocomplete="new-password"></div></div><button class="btn">Simpan Pengguna</button></form></div>';layout_end();break;

default:
    redirect_to('dashboard');
}
