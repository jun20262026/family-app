/**
 * 医院就诊记录功能（支持多文件 + 详情浮层）
 */

let currentMedicalPage = 1;

// 当前编辑状态下已上传的文件
let currentEditFiles = { paths: [], names: [] };

// ===================== 加载 & 渲染 =====================

function loadMedicalRecords(page) {
    currentMedicalPage = page || 1;

    const search    = document.getElementById('medical-search').value;
    const startDate = document.getElementById('medical-start-date').value;
    const endDate   = document.getElementById('medical-end-date').value;

    let url = 'api/medical_records.php?action=list&page=' + currentMedicalPage;
    if (search)    url += '&patient_name=' + encodeURIComponent(search);
    if (startDate)  url += '&start_date=' + startDate;
    if (endDate)    url += '&end_date=' + endDate;

    fetch(url)
        .then(res => res.json())
        .then(data => {
            if (!data.success) { alert('加载失败：' + data.msg); return; }
            renderMedicalTable(data.data);
            renderMedicalPagination(data.totalPages, data.page);
        })
        .catch(err => {
            console.error('加载就诊记录失败：', err);
            alert('加载失败，请检查网络');
        });
}

function renderMedicalTable(records) {
    const tbody = document.getElementById('medical-tbody');

    if (!records || records.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;color:#999;padding:20px;">暂无记录</td></tr>';
        return;
    }

    let html = '';
    records.forEach(r => {
        // 整行可点击查看详情（操作列除外）
        html += '<tr style="cursor:pointer;" onclick="showMedicalDetail(' + r.id + ')">';
        html += '<td style="font-weight:600;">' + escHtml(r.patient_name) + '</td>';
        html += '<td>' + escHtml(r.hospital) + '</td>';
        html += '<td>' + r.visit_date + '</td>';
        // 诊断摘要（最多显示30字）
        const diagSummary = (r.diagnosis || '').length > 30
            ? r.diagnosis.substring(0, 30) + '…'
            : (r.diagnosis || '-');
        html += '<td style="color:#555;">' + escHtml(diagSummary) + '</td>';
        html += '<td>¥' + parseFloat(r.cost || 0).toFixed(2) + '</td>';
        // 文件数量提示
        const fileCount = (r.file_paths || []).length;
        html += '<td>' + (fileCount > 0 ? '📎 ' + fileCount + ' 个文件' : '-') + '</td>';
        html += '<td>' + escHtml(r.record_by) + '</td>';
        // 操作按钮（阻止冒泡，避免触发详情）
        html += '<td onclick="event.stopPropagation()">';
        html += '<button class="btn" style="padding:4px 8px;font-size:0.75rem;" onclick="editMedicalRecord(' + r.id + ')">✏️</button> ';
        html += '<button class="btn" style="padding:4px 8px;font-size:0.75rem;color:#ef4444;" onclick="deleteMedicalRecord(' + r.id + ')">🗑️</button>';
        html += '</td>';
        html += '</tr>';
    });

    tbody.innerHTML = html;
}

function renderMedicalPagination(totalPages, currentPage) {
    const container = document.getElementById('medical-pagination');
    if (totalPages <= 1) { container.innerHTML = ''; return; }

    let html = '';
    if (currentPage > 1) {
        html += '<button class="btn" onclick="loadMedicalRecords(' + (currentPage - 1) + ')" style="padding:4px 10px;">« 上一页</button> ';
    }
    for (let i = 1; i <= totalPages; i++) {
        if (i === currentPage) {
            html += '<button class="btn btn-primary" style="padding:4px 10px;">' + i + '</button> ';
        } else {
            html += '<button class="btn" onclick="loadMedicalRecords(' + i + ')" style="padding:4px 10px;">' + i + '</button> ';
        }
    }
    if (currentPage < totalPages) {
        html += '<button class="btn" onclick="loadMedicalRecords(' + (currentPage + 1) + ')" style="padding:4px 10px;">下一页 »</button>';
    }
    container.innerHTML = html;
}

// ===================== 详情浮层 =====================

function showMedicalDetail(id) {
    fetch('api/medical_records.php?action=list')
        .then(res => res.json())
        .then(data => {
            if (!data.success) { alert('加载失败'); return; }
            const r = data.data.find(x => x.id == id);
            if (!r) { alert('记录不存在'); return; }

            // 标题
            document.getElementById('detail-patient-name').textContent =
                '🏥 ' + r.patient_name + ' — ' + r.hospital;

            // 主体内容
            const imageExts = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'];
            let bodyHtml = '';

            // 基本信息卡片
            bodyHtml += '<div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:16px;">';
            bodyHtml += '<div style="padding:10px;background:#f8fafc;border-radius:10px;"><div style="font-size:0.75rem;color:#999;">就诊日期</div><div style="font-weight:600;">' + r.visit_date + '</div></div>';
            bodyHtml += '<div style="padding:10px;background:#f0fdf4;border-radius:10px;"><div style="font-size:0.75rem;color:#999;">费用</div><div style="font-weight:700;color:#dc2626;font-size:1.1rem;">¥' + parseFloat(r.cost || 0).toFixed(2) + '</div></div>';
            bodyHtml += '</div>';

            // 诊断结果（完整内容）
            bodyHtml += '<div style="margin-bottom:16px;">';
            bodyHtml += '<div style="font-size:0.8rem;color:#999;margin-bottom:6px;">📝 诊断结果</div>';
            bodyHtml += '<div style="padding:12px;background:#f8fafc;border-radius:10px;line-height:1.7;white-space:pre-wrap;">' + escHtml(r.diagnosis || '无') + '</div>';
            bodyHtml += '</div>';

            // 文件列表（大图预览）
            const paths = r.file_paths || [];
            const names = r.file_names || [];
            if (paths.length > 0) {
                bodyHtml += '<div style="margin-bottom:8px;">';
                bodyHtml += '<div style="font-size:0.8rem;color:#999;margin-bottom:8px;">📎 附件（' + paths.length + '）</div>';
                bodyHtml += '<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(120px,1fr));gap:10px;">';

                paths.forEach((p, idx) => {
                    const fullUrl = p.startsWith('http')
                        ? p
                        : window.location.origin + '/' + p.replace(/^\/+/, '');
                    const ext = (names[idx] || p).split('.').pop().toLowerCase();
                    const isImage = imageExts.includes(ext);

                    bodyHtml += '<div style="border:1px solid #e5e7eb;border-radius:10px;overflow:hidden;">';
                    if (isImage) {
                        bodyHtml += '<a href="' + fullUrl + '" target="_blank" title="点击查看原图">';
                        bodyHtml += '<img src="' + fullUrl + '" style="width:100%;height:120px;object-fit:cover;cursor:pointer;" onerror="this.style.display=\'none\';">';
                        bodyHtml += '</a>';
                    } else {
                        bodyHtml += '<div style="height:120px;display:flex;align-items:center;justify-content:center;background:#f3f4f6;font-size:0.75rem;text-align:center;padding:8px;">' + escHtml(names[idx] || '文件') + '</div>';
                        bodyHtml += '<a href="' + fullUrl + '" target="_blank" style="display:block;text-align:center;padding:6px;font-size:0.75rem;color:#4f46e5;text-decoration:none;">📥 下载</a>';
                    }
                    bodyHtml += '</div>';
                });

                bodyHtml += '</div></div>';
            }

            // 记录人 & 记录时间
            bodyHtml += '<div style="margin-top:16px;font-size:0.75rem;color:#9ca3af;text-align:right;">记录人：' + escHtml(r.record_by || '-') + '</div>';

            document.getElementById('detail-body').innerHTML = bodyHtml;

            // 操作按钮
            let actionsHtml = '';
            actionsHtml += '<button class="btn" onclick="closeMedicalDetail();showMedicalForm(' + r.id + ')">✏️ 编辑</button>';
            actionsHtml += '<button class="btn btn-danger" onclick="closeMedicalDetail();deleteMedicalRecord(' + r.id + ')">🗑️ 删除</button>';
            actionsHtml += '<button class="btn" style="background:#e5e7eb;" onclick="closeMedicalDetail()">关闭</button>';
            document.getElementById('detail-actions').innerHTML = actionsHtml;

            // 显示浮层
            document.getElementById('medical-detail-overlay').style.display = 'flex';
        })
        .catch(() => alert('加载失败'));
}

function closeMedicalDetail(event) {
    // 支持 onclick="closeMedicalDetail(event)" 点击遮罩关闭
    if (event && event.target !== event.currentTarget) return;
    document.getElementById('medical-detail-overlay').style.display = 'none';
}

// ===================== 添加/编辑弹窗 =====================

function showMedicalForm(id) {
    currentEditFiles = { paths: [], names: [] };

    document.getElementById('medical-modal-title').textContent = id ? '修改就诊记录' : '添加就诊记录';
    document.getElementById('medical-id').value = id || '';
    document.getElementById('medical-patient-name').value = '';
    document.getElementById('medical-hospital').value = '';
    document.getElementById('medical-visit-date').value = new Date().toISOString().split('T')[0];
    document.getElementById('medical-diagnosis').value = '';
    document.getElementById('medical-cost').value = '';
    document.getElementById('medical-files').value = '';

    const preview = document.getElementById('medical-file-preview');
    if (preview) preview.innerHTML = '';

    if (id) {
        fetch('api/medical_records.php?action=list')
            .then(res => res.json())
            .then(data => {
                if (!data.success) return;
                const record = data.data.find(r => r.id == id);
                if (record) {
                    document.getElementById('medical-patient-name').value = record.patient_name;
                    document.getElementById('medical-hospital').value = record.hospital;
                    document.getElementById('medical-visit-date').value = record.visit_date;
                    document.getElementById('medical-diagnosis').value = record.diagnosis || '';
                    document.getElementById('medical-cost').value = record.cost || 0;

                    currentEditFiles.paths = record.file_paths || [];
                    currentEditFiles.names = record.file_names || [];
                    renderFilePreview(id);
                }
            });
    }

    document.getElementById('medical-modal-overlay').style.display = 'flex';
}

function renderFilePreview(recordId) {
    let container = document.getElementById('medical-file-preview');
    if (!container) {
        container = document.createElement('div');
        container.id = 'medical-file-preview';
        container.style.marginTop = '8px';
        document.getElementById('medical-files').parentNode.appendChild(container);
    }

    if (!currentEditFiles.paths || currentEditFiles.paths.length === 0) {
        container.innerHTML = '';
        return;
    }

    const imageExts = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'];
    let html = '<div style="font-size:0.8rem;color:#666;margin-bottom:6px;">已上传文件：</div>';
    html += '<div style="display:flex;flex-wrap:wrap;gap:8px;">';

    currentEditFiles.paths.forEach((p, idx) => {
        const fullUrl = p.startsWith('http')
            ? p
            : window.location.origin + '/' + p.replace(/^\/+/, '');
        const ext = (currentEditFiles.names[idx] || p).split('.').pop().toLowerCase();
        const isImage = imageExts.includes(ext);

        html += '<div style="position:relative;display:inline-block;">';
        if (isImage) {
            html += '<img src="' + fullUrl + '" style="width:64px;height:64px;object-fit:cover;border-radius:8px;border:1px solid #e5e7eb;">';
        } else {
            html += '<div style="width:64px;height:64px;display:flex;align-items:center;justify-content:center;background:#f3f4f6;border-radius:8px;font-size:0.6rem;text-align:center;padding:4px;">' + escHtml(currentEditFiles.names[idx] || '文件') + '</div>';
        }
        // 删除按钮
        html += '<span onclick="removeUploadedFile(' + (recordId || 0) + ',' + idx + ')" style="position:absolute;top:-6px;right:-6px;background:#ef4444;color:#fff;width:18px;height:18px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:0.7rem;cursor:pointer;">✕</span>';
        html += '</div>';
    });

    html += '</div>';
    container.innerHTML = html;
}

function removeUploadedFile(recordId, index) {
    if (!confirm('确定删除这个文件？')) return;

    if (recordId && recordId > 0) {
        const fd = new FormData();
        fd.append('action', 'delete_file');
        fd.append('id', recordId);
        fd.append('index', index);

        fetch('api/medical_records.php', { method: 'POST', body: fd })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    fetch('api/medical_records.php?action=list')
                        .then(r => r.json())
                        .then(d => {
                            if (d.success) {
                                const record = d.data.find(r => r.id == recordId);
                                if (record) {
                                    currentEditFiles.paths = record.file_paths || [];
                                    currentEditFiles.names = record.file_names || [];
                                }
                            }
                            renderFilePreview(recordId);
                            loadMedicalRecords(currentMedicalPage);
                        });
                } else {
                    alert('删除失败：' + data.msg);
                }
            })
            .catch(() => alert('删除失败'));
    } else {
        currentEditFiles.paths.splice(index, 1);
        currentEditFiles.names.splice(index, 1);
        renderFilePreview(0);
    }
}

function closeMedicalModal() {
    document.getElementById('medical-modal-overlay').style.display = 'none';
}

// ===================== 保存 & 删除 =====================

function saveMedicalRecord(event) {
    event.preventDefault();

    const id = document.getElementById('medical-id').value;
    const isEdit = id !== '';

    const formData = new FormData();
    formData.append('action', isEdit ? 'update' : 'add');
    if (isEdit) formData.append('id', id);
    formData.append('patient_name', document.getElementById('medical-patient-name').value);
    formData.append('hospital',   document.getElementById('medical-hospital').value);
    formData.append('visit_date',  document.getElementById('medical-visit-date').value);
    formData.append('diagnosis',   document.getElementById('medical-diagnosis').value);
    formData.append('cost',        document.getElementById('medical-cost').value);

    // 多文件上传
    const fileInput = document.getElementById('medical-files');
    for (let i = 0; i < fileInput.files.length; i++) {
        formData.append('files[]', fileInput.files[i]);
    }

    fetch('api/medical_records.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                closeMedicalModal();
                loadMedicalRecords(currentMedicalPage);
                alert('保存成功！');
            } else {
                alert('保存失败：' + data.msg);
            }
        })
        .catch(err => {
            console.error('保存失败：', err);
            alert('保存失败，请检查网络');
        });
}

function editMedicalRecord(id) {
    showMedicalForm(id);
}

function deleteMedicalRecord(id) {
    if (!confirm('确定要删除这条记录吗？')) return;

    const formData = new FormData();
    formData.append('action', 'delete');
    formData.append('id', id);

    fetch('api/medical_records.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                loadMedicalRecords(currentMedicalPage);
                alert('删除成功！');
            } else {
                alert('删除失败：' + data.msg);
            }
        })
        .catch(err => {
            console.error('删除失败：', err);
            alert('删除失败，请检查网络');
        });
}

// ===================== 工具函数 =====================

function escHtml(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

// ===================== 页面激活监听 =====================

document.addEventListener('DOMContentLoaded', function() {
    const medicalLink = document.querySelector('a[data-page="medical-records"]');
    if (medicalLink) {
        medicalLink.addEventListener('click', function() {
            setTimeout(() => loadMedicalRecords(1), 100);
        });
    }

    const medicalPage = document.getElementById('page-medical-records');
    if (medicalPage && medicalPage.classList.contains('active')) {
        loadMedicalRecords(1);
    }
});

const mp = document.getElementById('page-medical-records');
if (mp) {
    const observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            if (mutation.type === 'attributes' && mutation.attributeName === 'class') {
                if (mp.classList.contains('active')) {
                    loadMedicalRecords(1);
                }
            }
        });
    });
    observer.observe(mp, { attributes: true });
}

// ===== 公告发布 =====
// 注意：公告发布功能已在 index.php 中实现（showAnnForm、closeAnnForm、saveAnnouncement）
// 此处不再重复定义，避免冲突

// ===== 支出统计（医疗模块专用，避免覆盖 index.php 的同名函数） =====
function _medicalLoadExpenseStats() {
    // 加载支出统计信息
    const statsEl = document.getElementById('expense-stats');
    if (!statsEl) return;
    
    fetch('api/expense_stats.php')
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                statsEl.innerHTML = `
                    <div class="stats-grid">
                        <div class="stat-card"><div class="stat-value">¥${d.total_amount || 0}</div><div class="stat-label">总支出</div></div>
                        <div class="stat-card"><div class="stat-value">${d.total_count || 0}</div><div class="stat-label">总笔数</div></div>
                    </div>
                `;
            }
        })
        .catch(() => {});
}

// ===== 支出记录（医疗模块专用，避免覆盖 index.php 的同名函数） =====
function _medicalLoadExpenseRecords(page) {
    page = page || 1;
    const search = document.getElementById('er-search') ? document.getElementById('er-search').value : '';
    const category = document.getElementById('er-category-filter') ? document.getElementById('er-category-filter').value : '';
    const startDate = document.getElementById('er-start-date') ? document.getElementById('er-start-date').value : '';
    const endDate = document.getElementById('er-end-date') ? document.getElementById('er-end-date').value : '';
    
    let url = `api/expense_records.php?page=${page}`;
    if (search) url += `&search=${encodeURIComponent(search)}`;
    if (category) url += `&category=${encodeURIComponent(category)}`;
    if (startDate) url += `&start_date=${startDate}`;
    if (endDate) url += `&end_date=${endDate}`;
    
    fetch(url)
        .then(r => r.json())
        .then(d => {
            const tbody = document.getElementById('er-tbody');
            if (!tbody) return;
            
            if (d.success && d.records && d.records.length > 0) {
                let html = '';
                d.records.forEach(r => {
                    html += `<tr>
                        <td>${escHtml(r.person || '')}</td>
                        <td>${escHtml(r.category || '')}</td>
                        <td>¥${parseFloat(r.amount || 0).toFixed(2)}</td>
                        <td>${escHtml(r.purpose || '')}</td>
                        <td>${r.receipt_path ? '<a href="' + escHtml(r.receipt_path) + '" target="_blank">查看</a>' : '-'}</td>
                        <td>${escHtml(r.created_at || '')}</td>
                    </tr>`;
                });
                tbody.innerHTML = html;
                
                // 渲染分页
                if (document.getElementById('er-pagination')) {
                    let pg = '';
                    for (let i = 1; i <= (d.total_pages || 1); i++) {
                        if (i === page) {
                            pg += `<span style="margin:0 4px;font-weight:700;color:#4f46e5;">${i}</span>`;
                        } else {
                            pg += `<a href="javascript:void(0)" onclick="_medicalLoadExpenseRecords(${i})" style="margin:0 4px;color:#555;">${i}</a>`;
                        }
                    }
                    document.getElementById('er-pagination').innerHTML = pg;
                }
            } else {
                tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;color:#999;">暂无数据</td></tr>';
            }
        })
        .catch(() => {
            const tbody = document.getElementById('er-tbody');
            if (tbody) tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;color:red;">加载失败</td></tr>';
        });
}

// ===== Toast 提示 =====
function showToast(msg, type) {
    type = type || 'info';
    const colors = {
        'success': '#10b981',
        'error': '#ef4444',
        'warning': '#f59e0b',
        'info': '#3b82f6'
    };
    const toast = document.createElement('div');
    toast.style.cssText = `position:fixed;top:20px;right:20px;background:${colors[type] || colors['info']};color:#fff;padding:12px 20px;border-radius:8px;z-index:99999;font-size:0.9rem;box-shadow:0 4px 12px rgba(0,0,0,0.15);transition:all 0.3s;`;
    toast.textContent = msg;
    document.body.appendChild(toast);
    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateY(-10px)';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}
