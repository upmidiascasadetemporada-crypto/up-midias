<?php
// post.php - página pública com listagem de anúncios e modais de detalhes
// Não exige login

$dbDir = __DIR__ . '/data';
$dbFile = $dbDir . '/db.sqlite';
$uploadsDir = __DIR__ . '/uploads';
$wa_number = '557399311340'; // WhatsApp: +55 73 99311-340

if (!is_dir($dbDir)) mkdir($dbDir, 0777, true);
if (!is_dir($uploadsDir)) mkdir($uploadsDir, 0777, true);

try {
    $pdo = new PDO('sqlite:' . $dbFile);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // garante tabela caso ainda não exista
    $pdo->exec("CREATE TABLE IF NOT EXISTS posts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        title TEXT,
        location TEXT,
        price TEXT,
        description TEXT,
        images TEXT,
        created_at TEXT
    );");
} catch (Exception $e) {
    die('Erro de conexão com o banco: ' . htmlspecialchars($e->getMessage()));
}

$posts = $pdo->query('SELECT * FROM posts ORDER BY created_at DESC')->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Casas de Temporada — Anúncios</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { padding-top: 70px; }
    .card-img-top { height: 200px; object-fit: cover; }
    .whatsapp-fab { position: fixed; right: 18px; bottom: 18px; z-index: 9999; }
    .carousel-item img { height: 420px; object-fit: cover; width:100%; }
  </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top">
  <div class="container">
    <a class="navbar-brand" href="#">Casas de Temporada</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navmenu">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navmenu">
      <ul class="navbar-nav ms-auto">
        <li class="nav-item"><a class="nav-link" href="#anuncios">Anúncios</a></li>
        <li class="nav-item"><a class="nav-link" href="adm.php">Entrar</a></li>
      </ul>
    </div>
  </div>
</nav>

<div class="container">

  <!-- Carousel (top 5 posts) -->
  <?php if (count($posts) > 0): ?>
  <div id="mainCarousel" class="carousel slide mb-4" data-bs-ride="carousel">
    <div class="carousel-inner">
      <?php $active = 'active'; $count = 0; foreach ($posts as $p):
        $imgs = array_filter(array_map('trim', explode(',', $p['images'])));
        $img = $imgs[0] ?? 'https://via.placeholder.com/1200x420?text=Sem+imagem';
      ?>
      <div class="carousel-item <?php echo $active; ?>">
        <img src="<?php echo htmlspecialchars($img); ?>" alt="<?php echo htmlspecialchars($p['title']); ?>">
        <div class="carousel-caption d-none d-md-block bg-dark bg-opacity-50 rounded p-2">
          <h5><?php echo htmlspecialchars($p['title']); ?></h5>
          <p><?php echo htmlspecialchars($p['location']); ?> · <?php echo htmlspecialchars($p['price']); ?></p>
        </div>
      </div>
      <?php $active=''; $count++; if ($count>=5) break; endforeach; ?>
    </div>
    <button class="carousel-control-prev" type="button" data-bs-target="#mainCarousel" data-bs-slide="prev">
      <span class="carousel-control-prev-icon"></span>
    </button>
    <button class="carousel-control-next" type="button" data-bs-target="#mainCarousel" data-bs-slide="next">
      <span class="carousel-control-next-icon"></span>
    </button>
  </div>
  <?php endif; ?>

  <h2 id="anuncios" class="mb-3">Anúncios recentes</h2>
  <div class="row">
    <?php if (empty($posts)): ?>
      <div class="col-12"><div class="alert alert-info">Nenhum anúncio publicado ainda.</div></div>
    <?php endif; ?>

    <?php foreach ($posts as $p):
      $imgs = array_filter(array_map('trim', explode(',', $p['images'])));
      $thumb = $imgs[0] ?? 'https://via.placeholder.com/500x300?text=Sem+imagem';
    ?>
    <div class="col-md-4 mb-4">
      <div class="card h-100 shadow-sm">
        <img src="<?php echo htmlspecialchars($thumb); ?>" class="card-img-top" alt="">
        <div class="card-body d-flex flex-column">
          <h5 class="card-title"><?php echo htmlspecialchars($p['title']); ?></h5>
          <p class="card-text"><?php echo htmlspecialchars(mb_strimwidth($p['description'],0,120,'...')); ?></p>
          <p class="mt-auto"><strong><?php echo htmlspecialchars($p['price']); ?></strong></p>
          <!-- botão abre modal com mais detalhes -->
          <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modal<?php echo intval($p['id']); ?>">Ver detalhes</button>
          <a href="https://wa.me/<?php echo $wa_number; ?>?text=<?php echo urlencode('Olá, tenho interesse no anúncio: ' . $p['title']); ?>" target="_blank" class="btn btn-success btn-sm ms-2">WhatsApp</a>
        </div>
      </div>
    </div>

    <!-- Modal de detalhes para este post -->
    <div class="modal fade" id="modal<?php echo intval($p['id']); ?>" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title"><?php echo htmlspecialchars($p['title']); ?></h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
          </div>
          <div class="modal-body">
            <?php if (!empty($imgs)): ?>
              <div id="carouselPost<?php echo intval($p['id']); ?>" class="carousel slide mb-3" data-bs-ride="carousel">
                <div class="carousel-inner">
                  <?php $a='active'; foreach ($imgs as $im): ?>
                  <div class="carousel-item <?php echo $a; ?>">
                    <img src="<?php echo htmlspecialchars($im); ?>" class="d-block w-100" alt="">
                  </div>
                  <?php $a=''; endforeach; ?>
                </div>
                <button class="carousel-control-prev" type="button" data-bs-target="#carouselPost<?php echo intval($p['id']); ?>" data-bs-slide="prev">
                  <span class="carousel-control-prev-icon"></span>
                </button>
                <button class="carousel-control-next" type="button" data-bs-target="#carouselPost<?php echo intval($p['id']); ?>" data-bs-slide="next">
                  <span class="carousel-control-next-icon"></span>
                </button>
              </div>
            <?php endif; ?>

            <p><strong>Localização:</strong> <?php echo htmlspecialchars($p['location']); ?></p>
            <p><strong>Preço:</strong> <?php echo htmlspecialchars($p['price']); ?></p>
            <p><?php echo nl2br(htmlspecialchars($p['description'])); ?></p>
          </div>
          <div class="modal-footer">
            <a href="https://wa.me/<?php echo $wa_number; ?>?text=<?php echo urlencode('Olá, tenho interesse no anúncio: ' . $p['title']); ?>" target="_blank" class="btn btn-success">Entrar em contato (WhatsApp)</a>
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
          </div>
        </div>
      </div>
    </div>
    <!-- fim modal -->

    <?php endforeach; ?>
  </div>
</div>

<!-- WhatsApp floating button -->
<a class="whatsapp-fab" href="https://wa.me/<?php echo $wa_number; ?>" target="_blank">
  <button class="btn btn-success btn-lg rounded-circle shadow" style="width:64px;height:64px;">
    <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" fill="white" viewBox="0 0 16 16">
      <path d="M13.601 2.326A7.956 7.956 0 0 0 8.037.001C3.649.001.094 3.556.094 8c0 1.415.371 2.801 1.076 4.02L0 16l3.968-1.041A7.956 7.956 0 0 0 8.037 16C12.424 16 15.979 12.445 15.979 8c0-1.399-.362-2.738-1.0-3.674zM8.037 14.667c-1.35 0-2.626-.366-3.74-1.0l-.27-.153-2.35.617.63-2.29-.17-.28A6.667 6.667 0 1 1 8.037 14.667z"/>
    </svg>
  </button>
</a>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
