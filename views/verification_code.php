<?php
// 1. 先处理 PHP 逻辑
$classifications = getClassificationData();
$phoneNumbers = getPhoneNumberData();

// 处理消息提示...
if (isset($_SESSION['error_message'])) {
    $error_msg = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}
if (isset($_SESSION['success_message'])) {
    $success_msg = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

// 处理批量添加的回显数据...
$retained_bulk_data = isset($_SESSION['bulk_data_to_retain']) ? htmlspecialchars($_SESSION['bulk_data_to_retain']) : '';
unset($_SESSION['bulk_data_to_retain']);

$retained_bulk_category = isset($_SESSION['bulk_category_to_retain']) ? $_SESSION['bulk_category_to_retain'] : '';
unset($_SESSION['bulk_category_to_retain']);

$retained_bulk_days = isset($_SESSION['bulk_days_to_expire_to_retain']) ? htmlspecialchars($_SESSION['bulk_days_to_expire_to_retain']) : '1';
unset($_SESSION['bulk_days_to_expire_to_retain']);

// 处理成功弹窗数据
$success_details = null;
if (isset($_SESSION['bulk_add_success_details'])) {
    $success_details = $_SESSION['bulk_add_success_details'];
    unset($_SESSION['bulk_add_success_details']);
}
?>

<?php if (isset($error_msg)) echo '<p style="color: red; font-weight: bold;">' . $error_msg . '</p>'; ?>
<?php if (isset($success_msg)) echo '<p style="color: green; font-weight: bold;">' . $success_msg . '</p>'; ?>

<div id="success-modal" class="modal-overlay" style="display: none;">
    <div class="modal-content">
        <span class="modal-close-btn">&times;</span>
        <h3 id="modal-title" style="margin-top:0;">批量添加成功</h3>
        <div id="modal-body" class="modal-body"></div>
        <div class="modal-footer">
            <button id="modal-copy-btn" class="btn-primary">一键复制所有</button>
            <button id="modal-save-btn" class="btn-success">保存为TXT文件</button>
        </div>
    </div>
</div>

<h2>添加/编辑接码数据</h2>
<form id="verificationForm" method="post" action="admin.php?action=verification_code_save">
    <input type="hidden" name="original_code" id="originalCode">
    <input type="hidden" name="original_category" id="originalCategory">
    <div class="dhgl">
        <div class="form-item">
            <label for="category_name">选择分类:</label>
            <select id="category_name" name="category_name" required>
                <option value="">请选择一个分类</option>
                <?php foreach ($classifications as $classification): ?>
                    <option value="<?php echo htmlspecialchars($classification['id'] ?? ''); ?>"><?php echo htmlspecialchars($classification['category_name'] ?? ''); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-item">
            <label for="phonenumber">电话号:</label>
            <input type="text" id="phonenumber" name="phonenumber" required>
        </div>
        <div class="form-item">
            <label for="verification_code">查询码 (可选):</label>
            <input type="text" id="verification_code" name="verification_code" placeholder="留空则自动生成10位查询码">
        </div>
        <div class="form-item">
            <label for="days_to_expire">到期天数:</label>
            <input type="text" id="days_to_expire" name="days_to_expire" value="1" required>
        </div>
        <div class="form-item">
            <button type="submit" id="submitBtn" class="btn-primary">生成并保存</button>
        </div>
    </div>
</form>

<h2>批量添加接码数据</h2>
<form method="post" action="admin.php?action=verification_code_bulk_save">
    <div class="batch-add-grid">
        <div class="batch-add-left">
            <div class="form-item-horizontal">
                <label for="bulk_category_name">选择分类:</label>
                <select id="bulk_category_name" name="bulk_category_name" required>
                    <option value="">请选择一个分类</option>
                    <?php foreach ($classifications as $classification): ?>
                        <?php $cat_id = htmlspecialchars($classification['id'] ?? ''); ?>
                        <option value="<?php echo $cat_id; ?>" <?php if ($cat_id === $retained_bulk_category) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($classification['category_name'] ?? ''); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-item-horizontal">
                <label for="bulk_days_to_expire">到期天数:</label>
                <input type="text" id="bulk_days_to_expire" name="bulk_days_to_expire" value="<?php echo $retained_bulk_days; ?>" required>
            </div>

            <button type="submit" id="bulkSubmitBtn" class="btn-primary" style="width: 100%; margin-top: 5px; height: 36px;">批量保存</button>
        </div>

        <div class="batch-add-right">
            <textarea name="bulk_data" class="full-width-textarea" style="flex: 1; min-height: 180px;" placeholder="格式：&lt;code&gt;电话号 [查询码]&lt;/code&gt; (每行一条，分隔符可用---、空格或Tab)
示例:
1234567890 XYZ123
9876543210"></textarea>
        </div>
    </div>
</form>

<?php if (!empty($verificationData) || isset($_GET['search_term'])): ?>
    <h2>已添加的接码数据 <?php if (isset($_GET['search_term']) && !empty($_GET['search_term'])) echo "(搜索 ‘".htmlspecialchars($_GET['search_term'])."’ 的结果)"; ?></h2>
    
    <div class="toolbar">
        <button type="submit" form="bulkActionsForm" formaction="admin.php?action=verification_code_bulk_delete" onclick="return confirm('确定要删除所选数据吗？')" class="btn-danger">批量删除</button>
        <button type="submit" form="bulkActionsForm" formaction="admin.php?action=export_selected_combinations" class="btn-info">批量导出</button>
        <a href="admin.php?action=export_all_combinations" class="btn-info">导出所有</a>
        
        <form method="GET" action="admin.php" class="search-form">
            <input type="hidden" name="action" value="verification_code">
            <select name="filter_days" onchange="this.form.submit()" style="height:32px; border:1px solid #dcdfe6; border-radius:4px; margin-right:5px;">
                <option value="">全部状态</option>
                <option value="expired" <?php echo ($_GET['filter_days']??'')==='expired'?'selected':''; ?>>已过期</option>
                <option value="3" <?php echo ($_GET['filter_days']??'')==='3'?'selected':''; ?>>3天</option>
                <option value="10" <?php echo ($_GET['filter_days']??'')==='10'?'selected':''; ?>>10天</option>
                <option value="20" <?php echo ($_GET['filter_days']??'')==='20'?'selected':''; ?>>20天</option>
                <option value="30" <?php echo ($_GET['filter_days']??'')==='30'?'selected':''; ?>>30天</option>
            </select>
            <input type="text" id="searchInput" name="search_term" placeholder="搜索..." value="<?php echo htmlspecialchars($_GET['search_term'] ?? ''); ?>">
            <button type="submit" class="btn-primary">搜索</button>
            <?php if (isset($_GET['search_term']) && !empty($_GET['search_term'])): ?>
                <a href="admin.php?action=verification_code" class="btn-default">清空</a>
            <?php endif; ?>
        </form>
    </div>

    <?php if (empty($verificationData) && isset($_GET['search_term'])): ?>
        <p>根据您的搜索 "<?php echo htmlspecialchars($_GET['search_term']); ?>", 没有找到任何匹配的数据。</p>
    <?php elseif (!empty($verificationData)): ?>
        <form id="bulkActionsForm" method="post">
            <div class="table-responsive">
                <table id="verificationTable" class="data-table data-table-copy">
                    <thead>
                        <tr>
                            <th class="no-copy"><input type="checkbox" id="selectAll"> 全选</th>
                            <th data-sort="text" data-sort-dir="asc">查询码 <span class="sort-icon"></span></th>
                            <th data-sort="text">分类 <span class="sort-icon"></span></th>
                            <th data-sort="text">电话号 <span class="sort-icon"></span></th>
                            <th>User</th>
                            <th>关键词</th>
                            <th data-sort="date">上架日期 <span class="sort-icon"></span></th>
                            <th data-sort="text">到期时间 <span class="sort-icon"></span></th>
                            <th data-sort="numeric">剩余时间 <span class="sort-icon"></span></th>
                            <th>组合内容</th>
                            <th class="no-copy">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($verificationData as $code => $data): ?>
                            <?php
                                $is_expired = isset($data['is_expired']) && $data['is_expired'];
                                $releaseDate = $data['releasedate'][0] ?? null;
                                $expirationString = $data['expirationtime'][0] ?? '0天';
                                $daysToAdd = 0;
                                if (preg_match('/^(\d+(\.\d+)?)天$/', $expirationString, $matches)) {
                                    $daysToAdd = (float)$matches[1];
                                }
                                $expirationTimestamp = 0;
                                if ($releaseDate) {
                                    $releaseDateTime = new DateTime($releaseDate);
                                    $releaseDateTime->modify("+" . ($daysToAdd * 24 * 3600) . " seconds");
                                    $expirationTimestamp = $releaseDateTime->getTimestamp();
                                }
                            ?>
                            <tr class="<?php echo $is_expired ? 'expired-row' : ''; ?>">
                                <td class="no-copy"><input type="checkbox" name="selected_items[]" value="<?php echo htmlspecialchars($code); ?>"></td>
                                <td class="code-cell"><?php echo htmlspecialchars($code); ?></td>
                                <td class="category-cell">
                                    <?php echo htmlspecialchars($data['category'] ?? ''); ?>
                                    <?php if ($is_expired) echo '<span class="expired-badge">(已过期)</span>'; ?>
                                </td>
                                <td class="phonenumber-cell"><?php echo htmlspecialchars(explode('---', $data['combination'][0] ?? '')[0]); ?></td>
                                <td><?php echo htmlspecialchars($data['user'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars(implode(', ', $data['match_keywords'] ?? [])); ?></td>
                                <td class="releasedate-cell" data-sort-value="<?php echo htmlspecialchars(implode(', ', $data['releasedate'] ?? [])); ?>"><?php echo htmlspecialchars(implode(', ', $data['releasedate'] ?? [])); ?></td>
                                <td class="expirationtime-cell" data-sort-value="<?php echo $daysToAdd; ?>"><?php echo htmlspecialchars(implode(', ', $data['expirationtime'] ?? [])); ?></td>
                                <td class="time-left-cell" data-expiration="<?php echo $expirationTimestamp; ?>"></td>
                                <td class="combination-cell"><?php echo htmlspecialchars(implode(', ', $data['combination'] ?? [])); ?></td>
                                <td class="no-copy action-cell">
                                    <button type="button" class="edit-btn" data-code="<?php echo htmlspecialchars($code); ?>" data-category="<?php echo htmlspecialchars($data['category'] ?? ''); ?>">编辑</button>
                                    <a href="admin.php?action=verification_code_delete&code=<?php echo urlencode($code); ?>&category=<?php echo urlencode($data['category']); ?>" onclick="return confirm('确定要删除这条数据吗？');" class="delete-btn">删除</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div id="pagination-controls" class="pagination-controls"></div>
        </form>
    <?php endif; ?>
<?php else: ?>
    <p>当前没有已添加的接码数据。</p>
<?php endif; ?>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // --- Success Modal Logic ---
        const successModal = document.getElementById('success-modal');
        const modalTitle = document.getElementById('modal-title');
        const modalBody = document.getElementById('modal-body');
        const closeModalBtn = document.querySelector('.modal-close-btn');
        const copyAllBtn = document.getElementById('modal-copy-btn');
        const saveTxtBtn = document.getElementById('modal-save-btn');

        <?php if (isset($success_details) && !empty($success_details)): ?>
            const successData = <?php echo json_encode($success_details); ?>;
            if(modalTitle && modalBody) {
                modalTitle.textContent = `批量添加成功 ${successData.length} 个`;
                modalBody.textContent = successData.join('\n');
                successModal.style.display = 'flex';
            }
        <?php endif; ?>

        if(closeModalBtn) {
            closeModalBtn.addEventListener('click', () => {
                successModal.style.display = 'none';
            });
        }

        if(copyAllBtn) {
            copyAllBtn.addEventListener('click', () => {
                const textToCopy = modalBody.textContent;
                // 调用 assets/main.js 中的全局复制函数
                if(typeof copyTextToClipboard === 'function'){
                    copyTextToClipboard(textToCopy, copyAllBtn);
                }
            });
        }
        
        if(saveTxtBtn) {
            saveTxtBtn.addEventListener('click', () => {
                const textToSave = modalBody.textContent;
                const blob = new Blob([textToSave], { type: 'text/plain' });
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = 'successful_combinations.txt';
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(url);
            });
        }
        
        // --- Pagination Logic ---
        const table = document.getElementById('verificationTable');
        const tableBody = table ? table.querySelector('tbody') : null;
        const allRows = tableBody ? Array.from(tableBody.querySelectorAll('tr')) : [];
        const controlsContainer = document.getElementById('pagination-controls');
        let currentPage = 1;
        let rowsPerPage = 30;

        function setupPagination() {
            if (!controlsContainer) return;
            controlsContainer.innerHTML = `
                <div class="page-size-selector">
                    <select id="rows-per-page">
                        <option value="30">30</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                        <option value="200">200</option>
                        <option value="500">500</option>
                        <option value="999999">全部</option>
                    </select>
                    <label for="rows-per-page">条/页</label>
                </div>
                <div class="page-info" id="page-info"></div>
                <div class="page-buttons" id="page-buttons">
                    <button id="prev-page" disabled>&laquo; 上一页</button>
                    <span class="page-numbers" id="page-numbers"></span>
                    <button id="next-page">下一页 &raquo;</button>
                </div>
            `;

            const rowsPerPageSelect = document.getElementById('rows-per-page');
            rowsPerPageSelect.addEventListener('change', (e) => {
                rowsPerPage = parseInt(e.target.value, 10);
                currentPage = 1;
                render();
            });

            document.getElementById('prev-page').addEventListener('click', (e) => {
                e.preventDefault();
                if (currentPage > 1) { currentPage--; render(); }
            });

            document.getElementById('next-page').addEventListener('click', (e) => {
                 e.preventDefault();
                 const totalPages = Math.ceil(allRows.length / rowsPerPage);
                if (currentPage < totalPages) { currentPage++; render(); }
            });
            render();
        }

        function createPaginationButtons(totalPages) {
            const pageNumbersContainer = document.getElementById('page-numbers');
            if(!pageNumbersContainer) return;
            pageNumbersContainer.innerHTML = '';
            const maxVisibleButtons = 8;
            
            if (totalPages <= maxVisibleButtons) {
                for (let i = 1; i <= totalPages; i++) {
                    pageNumbersContainer.appendChild(createPageButton(i));
                }
            } else {
                pageNumbersContainer.appendChild(createPageButton(1));
                if (currentPage > 4) {
                    pageNumbersContainer.appendChild(createEllipsis());
                }

                let start = Math.max(2, currentPage - 2);
                let end = Math.min(totalPages - 1, currentPage + 2);

                if (currentPage <= 4) { start = 2; end = 6; }
                if (currentPage >= totalPages - 3) { start = totalPages - 5; end = totalPages - 1; }

                for (let i = start; i <= end; i++) {
                    pageNumbersContainer.appendChild(createPageButton(i));
                }

                if (currentPage < totalPages - 3) {
                    pageNumbersContainer.appendChild(createEllipsis());
                }
                pageNumbersContainer.appendChild(createPageButton(totalPages));
            }
        }

        function createPageButton(pageNumber) {
            const button = document.createElement('button');
            button.textContent = pageNumber;
            button.className = (pageNumber === currentPage) ? 'active' : '';
            button.addEventListener('click', (e) => {
                e.preventDefault(); 
                currentPage = pageNumber;
                render();
            });
            return button;
        }

        function createEllipsis() {
            const ellipsis = document.createElement('button');
            ellipsis.className = 'ellipsis';
            ellipsis.textContent = '...';
            ellipsis.disabled = true;
            ellipsis.style.border = 'none';
            ellipsis.style.background = 'none';
            return ellipsis;
        }

        function render() {
            const totalRows = allRows.length;
            const totalPages = Math.ceil(totalRows / rowsPerPage);
            const start = (currentPage - 1) * rowsPerPage;
            const end = start + rowsPerPage;
            
            if (tableBody) {
                tableBody.innerHTML = '';
                const visibleRows = allRows.slice(start, end);
                visibleRows.forEach(row => tableBody.appendChild(row));
            }

            const prevBtn = document.getElementById('prev-page');
            const nextBtn = document.getElementById('next-page');
            if (prevBtn) prevBtn.disabled = currentPage === 1;
            if (nextBtn) nextBtn.disabled = currentPage === totalPages || totalRows === 0;
            
            const pageInfo = document.getElementById('page-info');
            if (pageInfo) pageInfo.textContent = `共 ${totalRows} 条数据，第 ${currentPage} / ${totalPages > 0 ? totalPages : 1} 页`;
            
            createPaginationButtons(totalPages);
        }

        if (table && allRows.length > 0) {
            setupPagination();
        }
        
        // --- Edit Logic ---
        const form = document.getElementById('verificationForm');
        const originalCodeInput = document.getElementById('originalCode');
        const originalCategoryInput = document.getElementById('originalCategory');
        const categoryNameSelect = document.getElementById('category_name');
        const phonenumberInput = document.getElementById('phonenumber');
        const verificationCodeInput = document.getElementById('verification_code');
        const daysToExpireInput = document.getElementById('days_to_expire');
        const submitBtn = document.getElementById('submitBtn');

        document.querySelectorAll('.edit-btn').forEach(button => {
            button.addEventListener('click', function() {
                const row = this.closest('tr');
                const originalCode = row.querySelector('.code-cell').textContent.trim();
                const originalCategory = row.querySelector('.category-cell').textContent.replace('(已过期)', '').trim();
                const originalPhoneNumber = row.querySelector('.phonenumber-cell').textContent.trim();
                const originalDaysToExpire = row.querySelector('.expirationtime-cell').textContent.replace('天', '').trim();
                
                if(originalCodeInput) originalCodeInput.value = originalCode;
                if(originalCategoryInput) originalCategoryInput.value = originalCategory;
                if(form) form.action = 'admin.php?action=verification_code_edit';
                if(submitBtn) submitBtn.textContent = '保存修改';
                
                if(categoryNameSelect) {
                    for(let i=0; i<categoryNameSelect.options.length; i++){
                        if(categoryNameSelect.options[i].text === originalCategory){
                            categoryNameSelect.value = categoryNameSelect.options[i].value;
                            break;
                        }
                    }
                }
                
                if(phonenumberInput) phonenumberInput.value = originalPhoneNumber;
                if(verificationCodeInput) verificationCodeInput.value = originalCode;
                if(daysToExpireInput) daysToExpireInput.value = originalDaysToExpire;
                
                window.scrollTo({ top: 0, behavior: 'smooth' });
            });
        });

        // --- Time Left Calculation ---
        function updateTimeLeft() {
            const now = new Date();
            document.querySelectorAll('.time-left-cell').forEach(cell => {
                const expirationTimestamp = parseInt(cell.dataset.expiration, 10) * 1000;
                if (!expirationTimestamp) return;

                const timeDifference = expirationTimestamp - now.getTime();
                if (timeDifference <= 0) {
                    cell.textContent = '已过期';
                } else {
                    const totalSeconds = Math.floor(timeDifference / 1000);
                    const days = Math.floor(totalSeconds / (3600 * 24));
                    const hours = Math.floor((totalSeconds % (3600 * 24)) / 3600);
                    const minutes = Math.floor((totalSeconds % 3600) / 60);
                    const seconds = totalSeconds % 60;
                    cell.textContent = `${days}天${hours}:${minutes}:${seconds}`;
                    cell.dataset.sortValue = totalSeconds;
                }
            });
        }
        
        setInterval(updateTimeLeft, 1000);
        updateTimeLeft();

        // --- Sorting ---
        document.querySelectorAll('th[data-sort]').forEach(header => {
            header.addEventListener('click', function() {
                const sortType = this.dataset.sort;
                let sortDir = this.dataset.sortDir === 'asc' ? 'desc' : 'asc';
                const cellIndex = this.cellIndex;

                document.querySelectorAll('th[data-sort]').forEach(h => {
                    if (h !== this) h.dataset.sortDir = '';
                });
                this.dataset.sortDir = sortDir;

                allRows.sort((a, b) => {
                    const aCell = a.cells[cellIndex];
                    const bCell = b.cells[cellIndex];
                    let aValue = aCell.dataset.sortValue || aCell.textContent.trim();
                    let bValue = bCell.dataset.sortValue || bCell.textContent.trim();

                    if (sortType === 'numeric') {
                        aValue = parseFloat(aValue) || 0;
                        bValue = parseFloat(bValue) || 0;
                    } else if (sortType === 'date') {
                        aValue = new Date(aValue).getTime() || 0;
                        bValue = new Date(bValue).getTime() || 0;
                    }

                    if (aValue < bValue) return sortDir === 'asc' ? -1 : 1;
                    if (aValue > bValue) return sortDir === 'asc' ? 1 : -1;
                    return 0;
                });
                
                currentPage = 1; 
                render(); 
            });
        });
    });
</script>
