<?php ?>
  </main>
</div>

<nav class="bottom-nav">
  <div class="bottom-nav-row">
    <a href="index.php" class="bnav-item <?= ($activeNav??'')==='dashboard'?'active':''?>"><?= icon('dashboard',22) ?><span>داشبورد</span></a>
    <a href="product.php" class="bnav-item <?= ($activeNav??'')==='product'?'active':''?>"><?= icon('package',22) ?><span>محصولات</span></a>
    <a href="invoice.php" class="bnav-item <?= ($activeNav??'')==='invoice'?'active':''?>"><?= icon('invoice',22) ?><span>سفارش</span></a>
    <a href="payment.php" class="bnav-item <?= ($activeNav??'')==='payment'?'active':''?>"><?= icon('card',22) ?><span>تراکنش</span></a>
    <a href="bot_settings.php" class="bnav-item <?= ($activeNav??'')==='bot_settings'?'active':''?>"><?= icon('settings',22) ?><span>تنظیمات</span></a>
  </div>
</nav>
</div>

<script src="js/htmx.min.js"></script>
<script src="js/app.js"></script>
</body>
</html>
