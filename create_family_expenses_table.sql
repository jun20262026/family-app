-- 家庭支出记录表
CREATE TABLE IF NOT EXISTS family_expenses (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    purpose VARCHAR(200) NOT NULL COMMENT '用途',
    amount DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '总金额',
    aa_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '每人AA金额',
    executor VARCHAR(100) DEFAULT NULL COMMENT '执行人',
    paid_users TEXT DEFAULT NULL COMMENT '已付款用户ID列表(JSON数组)',
    expense_date DATE DEFAULT NULL COMMENT '支出日期',
    remark VARCHAR(500) DEFAULT NULL COMMENT '备注',
    created_by INT UNSIGNED NOT NULL COMMENT '创建者ID',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT NULL,
    INDEX idx_expense_date (expense_date),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='家庭支出记录表';

-- 支出凭证表
CREATE TABLE IF NOT EXISTS expense_receipts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    expense_id INT UNSIGNED NOT NULL COMMENT '关联支出ID',
    file_path VARCHAR(500) NOT NULL COMMENT '文件路径',
    file_name VARCHAR(200) NOT NULL COMMENT '原始文件名',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_expense_id (expense_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='支出凭证表';
