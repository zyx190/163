<h2>查询码管理</h2>

<?php
// 显示提示消息
if (isset($_SESSION['success_message'])) {
    echo '<p class="btn-success" style="display:block; padding: 10px; margin-bottom:15px; border-radius: 4px; color:#fff;">' . htmlspecialchars($_SESSION['success_message']) . '</p>';
    unset($_SESSION['success_message']);
}
if (isset($_SESSION['error_message'])) {
    echo '<p class="btn-danger" style="display:block; padding: 10px; margin-bottom:15px; border-radius: 4px; color:#fff;">' . htmlspecialchars($_SESSION['error_message']) . '</p>';
    unset($_SESSION['error_message']);
}
?>

<div class="toolbar">
    <div class="action-group" style="display: flex; gap: 10px;">
        <button type="button" class="btn-primary" id="btnShowAddModal">
            <i class="fas fa-plus"></i> 新建查询码
        </button>
        <button type="submit" form="managerForm" formaction="admin.php?action=used_codes_bulk_delete" onclick="return confirm('确定要删除选中的查询码吗？删除后这些码可能会被系统再次生成。')" class="btn-danger">
            <i class="fas fa-trash"></i> 批量删除
        </button>
    </div>

    <div class="search-box">
        <form method="GET" action="admin.php" class="search-form">
            <input type="hidden" name="action" value="code_manager">
            <label>搜索:</label>
            <input type="text" name="search_term" placeholder="输入查询码..." value="<?php echo htmlspecialchars($_GET['search_term'] ?? ''); ?>">
            <button type="submit" class="btn-primary">搜索</button>
            <?php if (!empty($_GET['search_term'])): ?>
                <a href="admin.php?action=code_manager" class="btn-default">清空</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<form id="managerForm" method="post">
    <div class="table-responsive">
        <table id="codesTable" class="data-table data-table-copy">
            <thead>
                <tr>
                    <th class="no-copy" width="40"><input type="checkbox" id="selectAll"></th>
                    <th data-sort="text">查询码 (Code) <span class="sort-icon"></span></th>
                    <th class="no-copy" width="100">操作</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($verificationData)): ?>
                    <tr><td colspan="3" style="text-align:center; padding: 20px; color: #999;">暂无数据</td></tr>
                <?php else: ?>
                    <?php foreach ($verificationData as $code): ?>
                        <tr>
                            <td class="no-copy"><input type="checkbox" name="selected_items[]" value="<?php echo htmlspecialchars($code); ?>"></td>
                            <td class="code-cell" style="font-weight:bold; color:#409EFF;"><?php echo htmlspecialchars($code); ?></td>
                            <td class="no-copy action-cell">
                                <a href="admin.php?action=used_codes_delete&code=<?php echo urlencode($code); ?>" onclick="return confirm('确定要删除这个查询码吗？');" class="delete-btn">
                                    删除
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <div id="pagination-controls" class="pagination-controls"></div>
</form>

<div id="codeManagerModal" class="custom-modal-overlay" style="display: none;">
    <div class="custom-modal-box">
        <span class="custom-close-btn" id="btnClockModal">&times;</span>
        <div class="custom-modal-header" style="border:none;">
            <h3>新建查询码</h3>
        </div>
        <form method="post" action="admin.php?action=used_codes_save">
            <div class="custom-modal-body">
                <div class="form-item">
                    <label for="verification_code">查询码:</label>
                    <input type="text" id="verification_code" name="verification_code" required placeholder="例如：ABC123456" style="width: 100%;">
                    <p style="font-size: 12px; color: #666; margin-top: 5px;">注意：手动添加后，系统在生成新码时将避开此码。</p>
                </div>
            </div>
            <div class="custom-modal-footer">
                <button type="button" class="btn-default" id="btnCancelModal" style="margin-right: 10px;">取消</button>
                <button type="submit" class="btn-primary">保存</button>
            </div>
        </form>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // --- 弹窗控制逻辑 ---
        const modal = document.getElementById('codeManagerModal');
        const openBtn = document.getElementById('btnShowAddModal');
        const closeBtn = document.getElementById('btnClockModal');
        const cancelBtn = document.getElementById('btnCancelModal');

        const openModal = () => { if(modal) modal.style.display = 'flex'; };
        const closeModal = () => { if(modal) modal.style.display = 'none'; };

        if (openBtn) openBtn.addEventListener('click', (e) => { e.preventDefault(); openModal(); });
        if (closeBtn) closeBtn.addEventListener('click', closeModal);
        if (cancelBtn) cancelBtn.addEventListener('click', closeModal);
        window.addEventListener('click', (e) => { if (e.target == modal) closeModal(); });

        // --- 表格排序和分页逻辑 ---
        const table = document.getElementById('codesTable');
        const tableBody = table ? table.querySelector('tbody') : null;
        const allRows = tableBody ? Array.from(tableBody.querySelectorAll('tr')) : [];
        const controlsContainer = document.getElementById('pagination-controls');
        let currentPage = 1;
        let rowsPerPage = 30;

        function render() {
            if (!tableBody) return;
            const totalRows = allRows.length;
            const totalPages = Math.ceil(totalRows / rowsPerPage);
            const start = (currentPage - 1) * rowsPerPage;
            const end = start + rowsPerPage;
            
            tableBody.innerHTML = '';
            const visibleRows = allRows.slice(start, end);
            
            if (visibleRows.length === 0 && totalRows === 0) {
                 tableBody.innerHTML = '<tr><td colspan="3" style="text-align:center; padding: 20px; color: #999;">暂无数据</td></tr>';
            } else {
                visibleRows.forEach(row => tableBody.appendChild(row));
            }
            updatePaginationControls(totalPages, totalRows);
        }

        function updatePaginationControls(totalPages, totalRows) {
            if (!controlsContainer) return;
            controlsContainer.innerHTML = `
                <div class="page-size-selector">
                    <select id="rows-per-page">
                        <option value="30" ${rowsPerPage==30?'selected':''}>30</option>
                        <option value="50" ${rowsPerPage==50?'selected':''}>50</option>
                        <option value="100" ${rowsPerPage==100?'selected':''}>100</option>
                        <option value="999999" ${rowsPerPage==999999?'selected':''}>全部</option>
                    </select>
                    <label>条/页</label>
                </div>
                <div class="page-info">共 ${totalRows} 条，第 ${currentPage}/${totalPages||1} 页</div>
                <div class="page-buttons">
                    <button type="button" id="prev-page" ${currentPage===1?'disabled':''}>上一页</button>
                    <button type="button" id="next-page" ${currentPage>=totalPages?'disabled':''}>下一页</button>
                </div>
            `;
            
            document.getElementById('rows-per-page').addEventListener('change', (e) => {
                rowsPerPage = parseInt(e.target.value); currentPage = 1; render();
            });
            document.getElementById('prev-page').addEventListener('click', () => {
                if(currentPage > 1) { currentPage--; render(); }
            });
            document.getElementById('next-page').addEventListener('click', () => {
                const total = Math.ceil(allRows.length / rowsPerPage);
                if(currentPage < total) { currentPage++; render(); }
            });
        }

        // 排序逻辑
        document.querySelectorAll('th[data-sort]').forEach(header => {
            header.addEventListener('click', function() {
                const sortType = this.dataset.sort;
                let sortDir = this.dataset.sortDir === 'asc' ? 'desc' : 'asc';
                const cellIndex = this.cellIndex;

                document.querySelectorAll('th[data-sort]').forEach(h => { if (h !== this) h.dataset.sortDir = ''; });
                this.dataset.sortDir = sortDir;

                allRows.sort((a, b) => {
                    const aValue = a.cells[cellIndex].textContent.trim();
                    const bValue = b.cells[cellIndex].textContent.trim();
                    if (aValue < bValue) return sortDir === 'asc' ? -1 : 1;
                    if (aValue > bValue) return sortDir === 'asc' ? 1 : -1;
                    return 0;
                });
                currentPage = 1; 
                render(); 
            });
        });

        render();
    });
</script>
