<?php
// post.php
// Painel de criação/edição/exclusão de posts (autossuficiente com SQLite)
session_start();

// Segurança: exige login
if (empty($_SESSION['admin_logged_in'])) {
    header('Location: adm.php');
    exit;
}

$dbDir = __DIR__ . '/data';
$dbFile = $dbDir . '/db.sqlite';
$uploadsDir = __DIR__ . '/uploads';
$maxUploadSize = 8 * 1024 * 1024; // 8MB por arquivo (ajuste se quiser)

// garantir diretórios
if (!is_dir($dbDir)) mkdir($dbDir, 0777, true);
if (!is_dir($uploadsDir)) mkdir($uploadsDir, 0777, true);

// conexão PDO
$pdo = new PDO('sqlite:' . $dbFile);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// garante tabela (poderia já existir via adm.php init)
$pdo->exec("
CREATE TABLE IF NOT EXISTS posts (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  title TEXT,
  location TEXT,
  price TEXT,
  description TEXT,
  images TEXT,
  created_at TEXT
);
");

// Função auxiliar para salvar uploads multiple -> retorna array de paths
function handleUploads($fieldName, $uploadsDir, $maxUploadSize) {
    $saved = [];
    if (empty($_FILES[$fieldName]) || !is_array($_FILES[$fieldName]['name'])) return $saved;

    for ($i = 0; $i < count($_FILES[$fieldName]['name']); $i++) {
        $error = $_FILES[$fieldName]['error'][$i];
        if ($error !== UPLOAD_ERR_OK) continue;
        $tmp = $_FILES[$fieldName]['tmp_name'][$i];
        $origName = basename($_FILES[$fieldName]['name'][$i]);
        $size = $_FILES[$fieldName]['size'][$i];
        if ($size > $maxUploadSize) continue;
        // simples sanitização do nome
        $safe = preg_replace('/[^a-zA-Z0-9._-]/', '_', $origName);
        $finalName = time() . '_' . bin2hex(random_bytes(6)) . '_' . $safe;
        $dest = $uploadsDir . '/' . $finalName;
        if (move_uploaded_file($tmp, $dest)) {
            // grava caminho relativo para uso no site (./uploads/...)
            $saved[] = 'uploads/' . $finalName;
        }
    }
    return $saved;
}

// Ações: create, update, delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'create') {
        $title = trim($_POST['title'] ?? '');
        $location = trim($_POST['location'] ?? '');
        $price = trim($_POST['price'] ?? '');
        $description = trim($_POST['description'] ?? '');

        $uploaded = handleUploads('images', $uploadsDir, $maxUploadSize);
        $imgs = implode(',', $uploaded);

        $stmt = $pdo->prepare('INSERT INTO posts (title, location, price, description, images, created_at) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$title, $location, $price, $description, $imgs, date('Y-m-d H:i:s')]);
        header('Location: post.php');
        exit;
    }

    if ($action === 'update') {
        $id = intval($_POST['id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $location = trim($_POST['location'] ?? '');
        $price = trim($_POST['price'] ?? '');
        $description = trim($_POST['description'] ?? '');

        // pega imagens existentes
        $stmt = $pdo->prepare('SELECT images FROM posts WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $existing = [];
        if ($row && $row['images']) {
            $existing = array_filter(array_map('trim', explode(',', $row['images'])));
        }

        $uploaded = handleUploads('images', $uploadsDir, $maxUploadSize);
        $all = array_merge($existing, $uploaded);
        $imgs = implode(',', $all);

        $stmt = $pdo->prepare('UPDATE posts SET title=?, location=?, price=?, description=?, images=? WHERE id=?');
        $stmt->execute([$title, $location, $price, $description, $imgs, $id]);
        header('Location: post.php');
        exit;
    }

    if ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        // Opcional: remover arquivos do servidor (aqui removemos)
        $stmt = $pdo->prepare('SELECT images FROM posts WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && $row['images']) {
            $imgs = array_filter(array_map('trim', explode(',', $row['images'])));
            foreach ($imgs as $im) {
                $path = __DIR__ . '/' . $im;
                if (file_exists($path)) @unlink($path);
            }
        }
        $stmt = $pdo->prepare('DELETE FROM posts WHERE id = ?');
        $stmt->execute([$id]);
        header('Location: post.php');
        exit;
    }
}

// Busca posts para listar
$posts = $pdo->query('SELECT * FROM posts ORDER BY created_at DESC')->fetchAll(PDO::FETCH_ASSOC);

// Se estiver editando (via GET ?edit=ID) pega o post
$editPost = null;
if (isset($_GET['edit'])) {
    $id = intval($_GET['edit']);
    $stmt = $pdo->prepare('SELECT * FROM posts WHERE id = ?');
    $stmt->execute([$id]);
    $editPost = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>
<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Painel de Posts</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    .card-img-top{height:140px;object-fit:cover}
    .thumb{width:90px;height:60px;object-fit:cover;border-radius:6px;border:1px solid #ddd}
  </style>
</head>
<body>
<nav class="navbar navbar-dark bg-dark">
  <div class="container-fluid">
    <span class="navbar-brand">Painel de Anúncios</span>
    <div class="d-flex align-items-center">
      <span class="text-light me-3"><?php echo htmlspecialchars($_SESSION['admin_user'] ?? ''); ?></span>
      <a class="btn btn-outline-light btn-sm me-2" href="adm.php?action=logout">Sair</a>
      <a class="btn btn-success btn-sm" href="../">Ver site</a>
    </div>
  </div>
</nav>

<div class="container py-4">
  <div class="row">
    <div class="col-lg-5">
      <div class="card mb-4">
        <div class="card-body">
          <h5 class="card-title"><?php echo $editPost ? 'Editar anúncio' : 'Criar novo anúncio'; ?></h5>
          <form method="post" enctype="multipart/form-data">
            <?php if ($editPost): ?>
              <input type="hidden" name="action" value="update">
              <input type="hidden" name="id" value="<?php echo intval($editPost['id']); ?>">
            <?php else: ?>
              <input type="hidden" name="action" value="create">
            <?php endif; ?>

            <div class="mb-2"><label class="form-label">Título</label>
              <input name="title" class="form-control" required value="<?php echo $editPost ? htmlspecialchars($editPost['title']) : ''; ?>">
            </div>
            <div class="mb-2"><label class="form-label">Localização</label>
              <input name="location" class="form-control" value="<?php echo $editPost ? htmlspecialchars($editPost['location']) : ''; ?>">
            </div>
            <div class="mb-2"><label class="form-label">Preço</label>
              <input name="price" class="form-control" value="<?php echo $editPost ? htmlspecialchars($editPost['price']) : ''; ?>">
            </div>
            <div class="mb-2"><label class="form-label">Descrição</label>
              <textarea name="description" class="form-control" rows="5"><?php echo $editPost ? htmlspecialchars($editPost['description']) : ''; ?></textarea>
            </div>
            <div class="mb-2"><label class="form-label">Imagens (múltiplas)</label>
              <input type="file" name="images[]" multiple class="form-control">
            </div>

            <?php if ($editPost && !empty($editPost['images'])): 
              $imgs = array_filter(array_map('trim', explode(',', $editPost['images']))); ?>
              <div class="mb-2">
                <label class="form-label">Imagens atuais</label>
                <div class="d-flex gap-2 flex-wrap">
                  <?php foreach ($imgs as $im): ?>
                    <img src="<?php echo htmlspecialchars($im); ?>" class="thumb" alt="">
                  <?php endforeach; ?>
                </div>
              </div>
            <?php endif; ?>

            <div class="d-grid">
              <button class="btn <?php echo $editPost ? 'btn-success' : 'btn-primary'; ?>">
                <?php echo $editPost ? 'Atualizar anúncio' : 'Salvar anúncio'; ?>
              </button>
            </div>
          </form>
        </div>
      </div>

      <?php if ($editPost): // link para cancelar edição ?>
        <div class="mb-4">
          <a href="post.php" class="btn btn-outline-secondary">Cancelar edição</a>
        </div>
      <?php endif; ?>
    </div>

    <div class="col-lg-7">
      <h5>Anúncios existentes</h5>
      <div class="row">
        <?php foreach ($posts as $p): 
          $imgs = array_filter(array_map('trim', explode(',', $p['images'])));
          $thumb = $imgs[0] ?? 'https://via.placeholder.com/500x300?text=Sem+imagem';
        ?>
        <div class="col-md-6">
          <div class="card mb-3 shadow-sm">
            <img src="<?php echo htmlspecialchars($thumb); ?>" class="card-img-top" alt="">
            <div class="card-body">
              <h6 class="card-title"><?php echo htmlspecialchars($p['title']); ?></h6>
              <p class="card-text"><?php echo htmlspecialchars(mb_strimwidth($p['description'], 0, 70, '...')); ?></p>
              <p><strong><?php echo htmlspecialchars($p['price']); ?></strong></p>
              <div class="d-flex gap-2">
                <a href="?edit=<?php echo intval($p['id']); ?>" class="btn btn-sm btn-outline-primary">Editar</a>

                <form method="post" onsubmit="return confirm('Apagar este anúncio? Esta ação removerá também as imagens.');" style="display:inline">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?php echo intval($p['id']); ?>">
                  <button class="btn btn-sm btn-outline-danger">Apagar</button>
                </form>
              </div>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
