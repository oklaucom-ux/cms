<?php
// Multi-Language Localization Helper
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$lang = $_SESSION['lang'] ?? 'en';
if (isset($_GET['lang'])) {
    $lang = $_GET['lang'];
    $_SESSION['lang'] = $lang;
}

$translations = [
    'en' => [
        'dashboard' => 'Dashboard',
        'employees' => 'Employees',
        'attendance' => 'Attendance',
        'payroll' => 'Payroll',
        'projects' => 'Projects',
        'tasks' => 'Tasks',
        'documents' => 'Documents',
        'settings' => 'Settings',
        'logout' => 'Logout'
    ],
    'es' => [
        'dashboard' => 'Tablero',
        'employees' => 'Empleados',
        'attendance' => 'Asistencia',
        'payroll' => 'Nómina',
        'projects' => 'Proyectos',
        'tasks' => 'Tareas',
        'documents' => 'Documentos',
        'settings' => 'Configuración',
        'logout' => 'Cerrar Sesión'
    ],
    'fr' => [
        'dashboard' => 'Tableau de bord',
        'employees' => 'Employés',
        'attendance' => 'Présence',
        'payroll' => 'Paie',
        'projects' => 'Projets',
        'tasks' => 'Tâches',
        'documents' => 'Documents',
        'settings' => 'Paramètres',
        'logout' => 'Déconnexion'
    ],
    'de' => [
        'dashboard' => 'Dashboard',
        'employees' => 'Mitarbeiter',
        'attendance' => 'Anwesenheit',
        'payroll' => 'Abrechnung',
        'projects' => 'Projekte',
        'tasks' => 'Aufgaben',
        'documents' => 'Dokumente',
        'settings' => 'Einstellungen',
        'logout' => 'Abmelden'
    ],
    'hi' => [
        'dashboard' => 'डैशबोर्ड',
        'employees' => 'कर्मचारी',
        'attendance' => 'उपस्थिति',
        'payroll' => 'वेतन',
        'projects' => 'परियोजनाएं',
        'tasks' => 'कार्य',
        'documents' => 'दस्तावेज़',
        'settings' => 'सेटिंग्स',
        'logout' => 'लॉगआउट'
    ]
];

function __t($key) {
    global $translations, $lang;
    return $translations[$lang][$key] ?? $translations['en'][$key] ?? $key;
}
