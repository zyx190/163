<?php // views/dashboard.php ?>
<h2>仪表盘</h2>
<p>欢迎来到管理后台，<?php echo htmlspecialchars($_SESSION['username'] ?? '用户'); ?>！</p>

<?php
// 此处依赖于 admin.php 传递的 $dashboardData 变量
if (isset($dashboardData)):
?>
<div class="dashboard-stats">
    <div class="stat-card">
        <h3>总电话个数</h3>
        <p class="stat-number"><?php echo htmlspecialchars($dashboardData['total_phones'] ?? 0); ?></p>
    </div>

    <div class="stat-card">
        <h3>已添加的接码数据</h3>
        <p class="stat-number"><?php echo htmlspecialchars($dashboardData['total_added_codes'] ?? 0); ?></p>
        <ul class="stat-list">
            <?php if (!empty($dashboardData['added_codes_by_category'])): ?>
                <?php foreach ($dashboardData['added_codes_by_category'] as $category => $count): ?>
                    <li><strong><?php echo htmlspecialchars($category); ?>:</strong> <?php echo htmlspecialchars($count); ?> 条</li>
                <?php endforeach; ?>
            <?php else: ?>
                <li>暂无分类数据</li>
            <?php endif; ?>
        </ul>
    </div>

    <div class="stat-card">
        <h3>已过期电话</h3>
        <p class="stat-number"><?php echo htmlspecialchars($dashboardData['total_expired_codes'] ?? 0); ?></p>
        <ul class="stat-list">
            <?php if (!empty($dashboardData['expired_codes_by_category'])): ?>
                <?php foreach ($dashboardData['expired_codes_by_category'] as $category => $count): ?>
                    <li><strong><?php echo htmlspecialchars($category); ?>:</strong> <?php echo htmlspecialchars($count); ?> 条</li>
                <?php endforeach; ?>
            <?php else: ?>
                <li>暂无过期分类数据</li>
            <?php endif; ?>
        </ul>
    </div>
</div>
<?php endif; ?>
