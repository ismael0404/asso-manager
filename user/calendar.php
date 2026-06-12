<?php
/**
 * Calendrier des activités
 * Fichier: user/calendar.php
 * Affichage calendrier mensuel avec activités
 */
$pageTitle = 'Calendrier';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

// Navigation mois
$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');

if ($month < 1) { $month = 12; $year--; }
if ($month > 12) { $month = 1; $year++; }

$firstDay = mktime(0, 0, 0, $month, 1, $year);
$daysInMonth = (int)date('t', $firstDay);
$startDayOfWeek = (int)date('N', $firstDay); // 1=Lundi, 7=Dimanche
$monthName = strftime('%B %Y', $firstDay);
$monthNames = ['', 'Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'];

$prevMonth = $month - 1;
$prevYear = $year;
if ($prevMonth < 1) { $prevMonth = 12; $prevYear--; }

$nextMonth = $month + 1;
$nextYear = $year;
if ($nextMonth > 12) { $nextMonth = 1; $nextYear++; }

// Récupérer les activités du mois
$startDate = sprintf('%04d-%02d-01', $year, $month);
$endDate = sprintf('%04d-%02d-%02d', $year, $month, $daysInMonth);

$stmt = $pdo->prepare("
    SELECT a.*, 
    (SELECT COUNT(*) FROM participations WHERE activity_id = a.id AND status = 'accepted') as participant_count
    FROM activities a 
    WHERE a.publication_status = 'published' 
    AND a.activity_date BETWEEN ? AND ?
    ORDER BY a.activity_date ASC
");
$stmt->execute([$startDate, $endDate]);
$activities = $stmt->fetchAll();

// Grouper par date
$activitiesByDate = [];
foreach ($activities as $act) {
    $day = (int)date('j', strtotime($act['activity_date']));
    $activitiesByDate[$day][] = $act;
}

// Détail d'une journée
$selectedDay = isset($_GET['day']) ? (int)$_GET['day'] : null;
$dayActivities = [];
if ($selectedDay && isset($activitiesByDate[$selectedDay])) {
    $dayActivities = $activitiesByDate[$selectedDay];
}

require_once __DIR__ . '/../includes/header.php';
?>

<section class="hero" style="padding:60px 24px 50px;">
    <div class="hero-content">
        <h1><i class="fas fa-calendar"></i> <span>Calendrier</span></h1>
        <p>Visualisez les activités de l'association mois par mois</p>
    </div>
</section>

<section class="section">
    <div class="container" style="max-width:1000px;">
        
        <!-- Navigation mois -->
        <div class="calendar-nav">
            <a href="?year=<?php echo $prevYear; ?>&month=<?php echo $prevMonth; ?>" class="btn btn-secondary btn-sm">
                <i class="fas fa-chevron-left"></i> Précédent
            </a>
            <h2 class="calendar-month-title"><?php echo $monthNames[$month] . ' ' . $year; ?></h2>
            <a href="?year=<?php echo $nextYear; ?>&month=<?php echo $nextMonth; ?>" class="btn btn-secondary btn-sm">
                Suivant <i class="fas fa-chevron-right"></i>
            </a>
        </div>

        <!-- Calendrier -->
        <div class="calendar-grid">
            <div class="calendar-header-row">
                <div class="cal-day-name">Lun</div>
                <div class="cal-day-name">Mar</div>
                <div class="cal-day-name">Mer</div>
                <div class="cal-day-name">Jeu</div>
                <div class="cal-day-name">Ven</div>
                <div class="cal-day-name cal-weekend">Sam</div>
                <div class="cal-day-name cal-weekend">Dim</div>
            </div>
            <div class="calendar-body">
                <?php
                // Cases vides au début
                for ($i = 1; $i < $startDayOfWeek; $i++) {
                    echo '<div class="cal-day cal-empty"></div>';
                }
                
                // Jours du mois
                $today = date('Y-m-d');
                for ($day = 1; $day <= $daysInMonth; $day++) {
                    $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $day);
                    $isToday = ($dateStr === $today);
                    $hasActivities = isset($activitiesByDate[$day]);
                    $isSelected = ($selectedDay === $day);
                    $dayOfWeek = (int)date('N', mktime(0, 0, 0, $month, $day, $year));
                    $isWeekend = ($dayOfWeek >= 6);
                    
                    $classes = 'cal-day';
                    if ($isToday) $classes .= ' cal-today';
                    if ($hasActivities) $classes .= ' cal-has-event';
                    if ($isSelected) $classes .= ' cal-selected';
                    if ($isWeekend) $classes .= ' cal-weekend-day';
                    
                    echo '<div class="' . $classes . '">';
                    if ($hasActivities) {
                        echo '<a href="?year=' . $year . '&month=' . $month . '&day=' . $day . '" class="cal-day-link">';
                    }
                    echo '<span class="cal-day-number">' . $day . '</span>';
                    if ($hasActivities) {
                        $count = count($activitiesByDate[$day]);
                        echo '<div class="cal-events-dots">';
                        for ($e = 0; $e < min($count, 3); $e++) {
                            $act = $activitiesByDate[$day][$e];
                            $dotClass = 'dot-' . $act['status'];
                            echo '<span class="cal-dot ' . $dotClass . '"></span>';
                        }
                        if ($count > 3) echo '<span class="cal-dot-more">+' . ($count - 3) . '</span>';
                        echo '</div>';
                        echo '<span class="cal-event-count">' . $count . ' act.</span>';
                        echo '</a>';
                    }
                    echo '</div>';
                }
                
                // Cases vides à la fin
                $lastDayOfWeek = (int)date('N', mktime(0, 0, 0, $month, $daysInMonth, $year));
                for ($i = $lastDayOfWeek; $i < 7; $i++) {
                    echo '<div class="cal-day cal-empty"></div>';
                }
                ?>
            </div>
        </div>

        <!-- Légende -->
        <div class="calendar-legend">
            <span><span class="cal-dot dot-upcoming"></span> À venir</span>
            <span><span class="cal-dot dot-ongoing"></span> En cours</span>
            <span><span class="cal-dot dot-completed"></span> Terminée</span>
        </div>

        <!-- Détails du jour sélectionné -->
        <?php if ($selectedDay && !empty($dayActivities)): ?>
        <div class="calendar-day-detail mt-3">
            <h3><i class="fas fa-calendar-day"></i> <?php echo $selectedDay . ' ' . $monthNames[$month] . ' ' . $year; ?></h3>
            <div class="cdd-list">
                <?php foreach ($dayActivities as $act): ?>
                <div class="cdd-item">
                    <div class="cdd-status-bar cdd-status-<?php echo e($act['status']); ?>"></div>
                    <div class="cdd-content">
                        <h4><a href="<?php echo BASE_URL; ?>/user/activities.php?detail=<?php echo $act['id']; ?>"><?php echo e($act['title']); ?></a></h4>
                        <div class="cdd-meta">
                            <span><i class="fas fa-users"></i> <?php echo $act['participant_count']; ?> participants</span>
                            <?php if ($act['location']): ?>
                            <span><i class="fas fa-map-marker-alt"></i> <?php echo e($act['location']); ?></span>
                            <?php endif; ?>
                            <span class="badge badge-<?php echo e($act['status']); ?>"><?php echo e(translateStatus($act['status'])); ?></span>
                        </div>
                        <p class="cdd-desc"><?php echo e(mb_strimwidth($act['description'], 0, 200, '...')); ?></p>
                        <a href="<?php echo BASE_URL; ?>/user/activities.php?detail=<?php echo $act['id']; ?>" class="btn btn-sm btn-primary"><i class="fas fa-eye"></i> Voir détails</a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php elseif ($selectedDay): ?>
        <div class="calendar-day-detail mt-3">
            <h3><i class="fas fa-calendar-day"></i> <?php echo $selectedDay . ' ' . $monthNames[$month] . ' ' . $year; ?></h3>
            <div class="empty-state" style="padding:30px;">
                <i class="fas fa-calendar-times" style="font-size:2rem;"></i>
                <p>Aucune activité prévue ce jour.</p>
            </div>
        </div>
        <?php endif; ?>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
