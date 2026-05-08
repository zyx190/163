<h2>电话管理</h2>
<?php
$phonenumbers = getPhoneNumberData();
?>

<h3>添加/编辑电话</h3>
<form id="phonenumberForm" method="post" action="admin.php?action=phonenumber_save">
    <div class="dhgl">
        <input type="hidden" name="original_id" id="originalId">
        <div class="form-item">
            <label for="host">Host:</label>
            <input type="text" id="host" name="host">
        </div>
        <div class="form-item">
            <label for="port">Port:</label>
            <input type="text" id="port" name="port" value="995">
        </div>
        <div class="form-item">
            <label for="user">邮箱 (user):</label>
            <input type="text" id="user" name="user">
        </div>
        <div class="form-item">
            <label for="pass">密码 (pass):</label>
            <input type="password" id="pass" name="pass">
        </div>
        <div class="form-item">
            <label for="match_sender">发件邮箱 (match_sender):</label>
            <input type="text" id="match_sender" name="match_sender">
        </div>
        <div class="form-item">
            <label for="phonenumber">电话号 (phonenumber):</label>
            <input type="text" id="phonenumber" name="phonenumber" required>
        </div>
        <div class="form-item">
            <button type="submit" id="submitBtn" class="btn-primary">添加电话</button>
        </div>
    </div>
</form>

<h3>批量添加电话</h3>
<div class="bulk-add-container">
    <form method="post" action="admin.php?action=phonenumber_bulk_save">
        <div class="dhgl">
            <div class="form-item" style="flex-grow: 1; max-width: 100%;">
                <label for="bulk_data">批量数据 (分隔符可用---, 空格, Tab):</label>
                <textarea id="bulk_data" name="bulk_data" style="width: 100%; min-width: 100%;" placeholder="每行一条，格式：host port 邮箱 密码 发件邮箱 电话号"></textarea>
            </div>
            <div class="form-item">
                <button type="submit" class="btn-primary">批量添加</button>
            </div>
        </div>
    </form>
</div>

<?php if (!empty($phonenumbers)): ?>
    <h3>已添加的电话</h3>
    
    <div class="toolbar">
        <button type="submit" form="phonenumberTableForm" formaction="admin.php?action=phonenumber_bulk_delete" class="btn-danger" onclick="return confirm('确定要删除选中的电话吗？');">批量删除</button>
        <button type="submit" form="phonenumberTableForm" formaction="admin.php?action=export_selected_phonenumbers" class="btn-info">导出所选</button>
        <a href="admin.php?action=export_phonenumbers" class="btn-info">导出全部</a>
        
        <div class="search-box">
            <label for="search" style="margin-bottom: 0;">搜索:</label>
            <input type="text" id="search" placeholder="输入邮箱、发件邮箱或电话号...">
        </div>
    </div>

    <form id="phonenumberTableForm" method="post">
        <div class="table-responsive">
            <table id="phoneTable" class="data-table data-table-copy">
                <thead>
                    <tr>
                        <th class="no-copy"><input type="checkbox" id="selectAll"> 全选</th>
                        <th>Host</th>
                        <th>Port</th>
                        <th class="search-col">邮箱 (user)</th>
                        <th>密码 (pass)</th>
                        <th class="search-col">发件邮箱 (match_sender)</th>
                        <th class="search-col">电话号 (phonenumber)</th>
                        <th class="no-copy">操作</th>
                    </tr>
                </thead>
                <tbody id="tableBody">
                <?php foreach ($phonenumbers as $number): ?>
                    <?php
                    $host = htmlspecialchars($number['host'] ?? '');
                    $port = htmlspecialchars($number['port'] ?? '');
                    $user = htmlspecialchars($number['user'] ?? '');
                    $pass = htmlspecialchars($number['pass'] ?? '');
                    $match_sender = htmlspecialchars($number['match_sender'] ?? '');
                    $phonenumber = htmlspecialchars($number['phonenumber'] ?? '');
                    $id = $phonenumber;
                    ?>
                    <tr>
                        <td class="no-copy"><input type="checkbox" name="selected_items[]" value="<?php echo $id; ?>"></td>
                        <td><?php echo $host; ?></td>
                        <td><?php echo $port; ?></td>
                        <td class="search-col"><?php echo $user; ?></td>
                        <td><?php echo $pass; ?></td>
                        <td class="search-col"><?php echo $match_sender; ?></td>
                        <td class="search-col"><?php echo $phonenumber; ?></td>
                        <td class="no-copy action-cell">
                            <button type="button" class="edit-btn" 
                               data-host="<?php echo $host; ?>" 
                               data-port="<?php echo $port; ?>"
                               data-user="<?php echo $user; ?>" 
                               data-pass="<?php echo $pass; ?>"
                               data-match_sender="<?php echo $match_sender; ?>"
                               data-phonenumber="<?php echo $phonenumber; ?>"
                               data-id="<?php echo $id; ?>">编辑</button>
                            <a href="admin.php?action=phonenumber_delete&id=<?php echo urlencode($id); ?>" onclick="return confirm('确定要删除这个电话吗？');" class="delete-btn">删除</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div id="pagination-controls" class="pagination-controls"></div>
    </form>
<?php else: ?>
    <p>当前没有已添加的电话信息。</p>
<?php endif; ?>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const table = document.getElementById('phoneTable');
        const tableBody = table ? table.querySelector('tbody') : null;
        const allRows = tableBody ? Array.from(tableBody.querySelectorAll('tr')) : [];
        const controlsContainer = document.getElementById('pagination-controls');
        let currentPage = 1;
        let rowsPerPage = 30;

        // 分页组件逻辑
        function setupPagination() {
            if (!controlsContainer) return;
            controlsContainer.innerHTML = `
                <div class="page-size-selector" style="display:flex; align-items:center; gap:5px;">
                    <select id="rows-per-page">
                        <option value="30">30</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                        <option value="200">200</option>
                        <option value="500">500</option>
                        <option value="999999">全部</option>
                    </select>
                    <label for="rows-per-page" style="margin:0; font-weight:normal;">条/页</label>
                </div>
                <div class="page-info" id="page-info"></div>
                <div class="page-buttons" style="display:flex; gap:5px;">
                    <button id="prev-page" disabled>&laquo; 上一页</button>
                    <span class="page-numbers" id="page-numbers" style="display:flex; gap:2px;"></span>
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
            pageNumbersContainer.innerHTML = '';
            const maxVisibleButtons = 5; // 移动端适合显示较少的页码按钮
            
            if (totalPages <= maxVisibleButtons) {
                for (let i = 1; i <= totalPages; i++) {
                    pageNumbersContainer.appendChild(createPageButton(i));
                }
            } else {
                pageNumbersContainer.appendChild(createPageButton(1));
                if (currentPage > 3) {
                    pageNumbersContainer.appendChild(createEllipsis());
                }
                let start = Math.max(2, currentPage - 1);
                let end = Math.min(totalPages - 1, currentPage + 1);
                if (currentPage <= 3) { start = 2; end = 4; }
                if (currentPage >= totalPages - 2) { start = totalPages - 3; end = totalPages - 1; }
                for (let i = start; i <= end; i++) {
                    pageNumbersContainer.appendChild(createPageButton(i));
                }
                if (currentPage < totalPages - 2) {
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
            ellipsis.style.background = 'transparent';
            return ellipsis;
        }

        function render() {
            const searchTerm = document.getElementById('search') ? document.getElementById('search').value.toLowerCase() : '';
            let visibleRows = allRows;
            
            if (searchTerm) {
                visibleRows = allRows.filter(row => {
                    const userCell = row.cells[3] ? row.cells[3].textContent.toLowerCase() : '';
                    const senderCell = row.cells[5] ? row.cells[5].textContent.toLowerCase() : '';
                    const phoneCell = row.cells[6] ? row.cells[6].textContent.toLowerCase() : '';
                    return userCell.includes(searchTerm) || senderCell.includes(searchTerm) || phoneCell.includes(searchTerm);
                });
            }

            const totalRows = visibleRows.length;
            const totalPages = Math.ceil(totalRows / rowsPerPage);
            const start = (currentPage - 1) * rowsPerPage;
            const end = start + rowsPerPage;
            
            allRows.forEach(row => row.style.display = 'none');

            const currentRows = visibleRows.slice(start, end);
            currentRows.forEach(row => row.style.display = '');

            const prevBtn = document.getElementById('prev-page');
            const nextBtn = document.getElementById('next-page');
            if(prevBtn) prevBtn.disabled = currentPage === 1;
            if(nextBtn) nextBtn.disabled = currentPage === totalPages || totalRows === 0;
            
            const pageInfo = document.getElementById('page-info');
            if(pageInfo) pageInfo.textContent = `共 ${totalRows} 条，第 ${currentPage} / ${totalPages > 0 ? totalPages : 1} 页`;
            
            createPaginationButtons(totalPages > 0 ? totalPages : 1);
        }

        if (table && allRows.length > 0) {
            setupPagination();
        }

        // 编辑按钮赋值逻辑
        const editButtons = document.querySelectorAll('.edit-btn');
        const form = document.getElementById('phonenumberForm');
        const originalIdInput = document.getElementById('originalId');
        const hostInput = document.getElementById('host');
        const portInput = document.getElementById('port');
        const userInput = document.getElementById('user');
        const passInput = document.getElementById('pass');
        const matchSenderInput = document.getElementById('match_sender');
        const phonenumberInput = document.getElementById('phonenumber');
        const submitBtn = document.getElementById('submitBtn');

        editButtons.forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                originalIdInput.value = this.getAttribute('data-id');
                hostInput.value = this.getAttribute('data-host');
                portInput.value = this.getAttribute('data-port');
                userInput.value = this.getAttribute('data-user');
                passInput.value = this.getAttribute('data-pass');
                matchSenderInput.value = this.getAttribute('data-match_sender');
                phonenumberInput.value = this.getAttribute('data-phonenumber');
                
                form.action = 'admin.php?action=phonenumber_edit';
                submitBtn.textContent = '保存修改';
                window.scrollTo({ top: 0, behavior: 'smooth' });
            });
        });

        // 提交表单前的选择校验
        const tableForm = document.getElementById('phonenumberTableForm');
        if(tableForm) {
            tableForm.addEventListener('submit', function(e) {
                const action = e.submitter ? e.submitter.getAttribute('formaction') : '';
                if (action && (action.includes('bulk_delete') || action.includes('export_selected'))) {
                    const selected = Array.from(document.querySelectorAll('#phonenumberTableForm input[name="selected_items[]"]:checked'));
                    if (selected.length === 0) {
                        alert('请至少选择一条数据进行操作。');
                        e.preventDefault();
                        return false;
                    }
                }
            });
        }
        
        // 搜索框触发重新渲染
        const searchInput = document.getElementById('search');
        if(searchInput){
            searchInput.addEventListener('keyup', function() {
                currentPage = 1;
                render();
            });
        }
    });
</script>
