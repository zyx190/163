document.addEventListener('DOMContentLoaded', function() {
    // 1. 移动端侧边栏切换逻辑
    const menuToggle = document.getElementById('menu-toggle');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('overlay');
    
    if(menuToggle && sidebar && overlay) {
        menuToggle.addEventListener('click', function(e){
            e.stopPropagation();
            sidebar.classList.toggle('show');
            overlay.classList.toggle('show');
        });
        overlay.addEventListener('click', function(){
            sidebar.classList.remove('show');
            overlay.classList.remove('show');
        });
    }

    // 2. 侧边栏子菜单下拉逻辑
    const toggleSubmenu = document.getElementById('toggle-submenu');
    if(toggleSubmenu) {
        toggleSubmenu.addEventListener('click', function(e){
            e.preventDefault();
            const submenu = this.nextElementSibling;
            const icon = this.querySelector('.pull-right');
            if(submenu) {
                if (submenu.style.display === 'block') {
                    submenu.style.display = 'none';
                    if(icon) { icon.classList.remove('fa-angle-up'); icon.classList.add('fa-angle-down'); }
                } else {
                    submenu.style.display = 'block';
                    if(icon) { icon.classList.remove('fa-angle-down'); icon.classList.add('fa-angle-up'); }
                }
            }
        });
    }

    // 3. 全局复制到剪贴板功能
    window.copyTextToClipboard = function(text, cell) {
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text).then(() => showCopySuccess(cell), () => showCopyError());
        } else {
            const textArea = document.createElement("textarea");
            textArea.value = text;
            textArea.style.position = "absolute";
            textArea.style.left = "-9999px";
            document.body.appendChild(textArea);
            textArea.select();
            try {
                document.execCommand('copy');
                showCopySuccess(cell);
            } catch (err) {
                showCopyError();
            } finally {
                document.body.removeChild(textArea);
            }
        }
    }

    window.showCopySuccess = function(cell) {
        if (cell && cell.classList) {
            cell.classList.add('copy-feedback');
            setTimeout(() => cell.classList.remove('copy-feedback'), 200);
        }
        const notification = document.createElement('div');
        notification.textContent = '已复制!';
        notification.style.position = 'fixed';
        notification.style.top = '20px';
        notification.style.left = '50%';
        notification.style.transform = 'translateX(-50%)';
        notification.style.background = '#67C23A';
        notification.style.color = 'white';
        notification.style.padding = '8px 20px';
        notification.style.borderRadius = '4px';
        notification.style.zIndex = '9999';
        notification.style.transition = 'opacity 0.4s';
        document.body.appendChild(notification);
        setTimeout(() => {
            notification.style.opacity = '0';
            setTimeout(() => notification.remove(), 400);
        }, 1200);
    }
    
    window.showCopyError = function() {
        alert("复制失败，请手动复制。");
    }

    // 绑定所有的带有 copy 类的表格单元格
    document.querySelectorAll('.data-table-copy').forEach(table => {
        table.addEventListener('click', function(e) {
            const cell = e.target.closest('td');
            if (!cell || cell.classList.contains('no-copy')) return;
            const textToCopy = cell.textContent.trim();
            if (textToCopy) { copyTextToClipboard(textToCopy, cell); }
        });
    });

    // 4. 全选/反选逻辑
    const selectAllCheckbox = document.getElementById('selectAll');
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            document.querySelectorAll('input[name="selected_items[]"]').forEach(checkbox => {
                const row = checkbox.closest('tr');
                if(row && row.style.display !== 'none') {
                    checkbox.checked = this.checked;
                }
            });
        });
    }
});
