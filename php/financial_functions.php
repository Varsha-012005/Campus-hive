<?php

function getStudentBalance($student_id) {
    global $pdo;
    
    $balance_query = $pdo->prepare("
        SELECT 
            SUM(CASE 
                WHEN type IN ('tuition', 'other') THEN amount
                WHEN type IN ('payment', 'scholarship', 'financial_aid', 'refund') THEN -amount
                ELSE 0
            END) as balance_due
        FROM financial_transactions
        WHERE student_id = ? AND status = 'pending'
    ");
    $balance_query->execute([$student_id]);
    $balance_data = $balance_query->fetch();
    
    return $balance_data['balance_due'] ?? 0;
}

function getStudentTransactions($student_id, $limit = 10) {
    global $pdo;
    
    // For security, ensure limit is an integer
    $limit = (int)$limit;
    
    // Modified query with LIMIT as part of the SQL string (not parameterized)
    $query = $pdo->prepare("
        SELECT *, 
            CASE 
                WHEN type IN ('payment', 'scholarship', 'financial_aid', 'refund') THEN -amount
                ELSE amount
            END as display_amount
        FROM financial_transactions
        WHERE student_id = ?
        ORDER BY transaction_date DESC
        LIMIT $limit
    ");
    $query->execute([$student_id]);
    
    return $query->fetchAll();
}

function getStudentFinancialAid($student_id, $limit = 5) {
    global $pdo;
    
    // For security, ensure limit is an integer
    $limit = (int)$limit;
    
    // Modified query with LIMIT as part of the SQL string (not parameterized)
    $query = $pdo->prepare("
        SELECT *
        FROM financial_transactions
        WHERE student_id = ? AND type IN ('scholarship', 'financial_aid')
        ORDER BY transaction_date DESC
        LIMIT $limit
    ");
    $query->execute([$student_id]);
    
    return $query->fetchAll();
}