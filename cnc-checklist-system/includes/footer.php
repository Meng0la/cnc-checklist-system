<?php if (is_logged_in()): ?>
    </main>
    <footer class="footer">
      <span><?= APP_NAME ?> &copy; <?= date('Y') ?></span>
    </footer>
  </div>
</div>
<?php else: ?>
</main>
<footer class="footer">
  <span><?= APP_NAME ?> &copy; <?= date('Y') ?></span>
</footer>
<?php endif; ?>
<?php if (is_logged_in()): ?>
<script>
  window.APP_SESSION = {
    idleTimeoutMs: <?= SESSAO_IDLE_SEGUNDOS * 1000 ?>,
    logoutUrl: <?= json_encode(base_url('logout.php?motivo=inatividade')) ?>
  };
</script>
<?php endif; ?>
<script src="<?= base_url('assets/js/app.js') ?>"></script>
</body>
</html>
