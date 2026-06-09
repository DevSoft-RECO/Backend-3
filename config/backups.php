<?php

// ==========================================
// === BACKUP SYSTEM ===
// Configuración del Sistema de Respaldos (Hija)
// ==========================================

return [
    'token' => env('BACKUP_SHARED_TOKEN', 'secreto_backup_hija_app2_2026'),
    'mysqldump_path' => env('BACKUP_MYSQLDUMP_PATH', 'mysqldump'),
];
