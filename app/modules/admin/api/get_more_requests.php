<?php
session_start();
require_once __DIR__ . '/../../../../config/database.php';
require_once __DIR__ . '/../../../../config/leave_types.php';

// Clean output - no debugging

// Check if user is logged in and is admin/manager/director
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin','manager','director'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if (!isset($_GET['offset']) || !is_numeric($_GET['offset'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid offset parameter']);
    exit();
}

$offset = (int)$_GET['offset'];
$limit = 15; // Load 15 more requests at a time

// Build query with filters (same as main page)
$where_conditions = [];
$params = [];

if (isset($_GET['status']) && $_GET['status'] !== '') {
    $where_conditions[] = "lr.status = ?";
    $params[] = $_GET['status'];
}

if (isset($_GET['employee']) && $_GET['employee'] !== '') {
    $where_conditions[] = "e.name LIKE ?";
    $params[] = '%' . $_GET['employee'] . '%';
}

if (isset($_GET['leave_type']) && $_GET['leave_type'] !== '') {
    $where_conditions[] = "lr.leave_type = ?";
    $params[] = $_GET['leave_type'];
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

try {
    // Get additional leave requests with filters
    $query = "
        SELECT lr.*, e.name as employee_name, e.email as employee_email, e.department,
               dept_approver.name as dept_head_name, director_approver.name as director_name, admin_approver.name as admin_name,
               CASE 
                   WHEN lr.approved_days IS NOT NULL AND lr.approved_days > 0 
                   THEN lr.approved_days
                   ELSE lr.days_requested
               END as actual_days_approved
        FROM leave_requests lr 
        JOIN employees e ON lr.employee_id = e.id 
        LEFT JOIN employees dept_approver ON lr.dept_head_approved_by = dept_approver.id
        LEFT JOIN employees director_approver ON lr.director_approved_by = director_approver.id
        LEFT JOIN employees admin_approver ON lr.admin_approved_by = admin_approver.id
        " . $where_clause . "
        ORDER BY lr.created_at DESC
        LIMIT " . intval($limit) . " OFFSET " . intval($offset);
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $additional_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get leave types configuration
    $leaveTypes = getLeaveTypes();
    
    // Generate HTML for additional requests
    $html = '';
    foreach ($additional_requests as $request) {
        $start = new DateTime($request['start_date']);
        $end = new DateTime($request['end_date']);
        $days = $start->diff($end)->days + 1;
        
        // Status badge
        $statusBadge = '';
        switch ($request['status']) {
            case 'approved':
                $statusBadge = '<span class="bg-green-500/20 text-green-400 px-3 py-1 rounded-full text-xs font-semibold uppercase tracking-wide">Approved</span>';
                break;
            case 'rejected':
                $statusBadge = '<span class="bg-red-500/20 text-red-400 px-3 py-1 rounded-full text-xs font-semibold uppercase tracking-wide">Rejected</span>';
                break;
            case 'pending':
                $statusBadge = '<span class="bg-yellow-500/20 text-yellow-400 px-3 py-1 rounded-full text-xs font-semibold uppercase tracking-wide">Pending</span>';
                break;
            default:
                $statusBadge = '<span class="bg-slate-500/20 text-slate-400 px-3 py-1 rounded-full text-xs font-semibold uppercase tracking-wide">' . ucfirst($request['status']) . '</span>';
        }
        
        // Generate HTML matching the exact original table structure
        $html .= '
        <tr data-request-id="' . $request['id'] . '" class="border-b border-slate-700/30 hover:bg-slate-700/30 transition-colors">
            <td class="py-4 px-4">
                <div class="font-semibold text-white">' . htmlspecialchars($request['employee_name']) . '</div>
            </td>
            <td class="py-4 px-4 text-slate-300">' . htmlspecialchars($request['department']) . '</td>
            <td class="py-4 px-4">
                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-primary/20 text-primary border border-primary/30">
                    ' . getLeaveTypeDisplayName($request['leave_type'], $request['original_leave_type'] ?? null, $leaveTypes) . '
                </span>
            </td>
            <td class="py-4 px-4 text-slate-300">' . date('M d, Y', strtotime($request['start_date'])) . '</td>
            <td class="py-4 px-4 text-slate-300">
                ' . (($request['status'] === 'approved' && $request['approved_days'] && $request['approved_days'] != $request['days_requested']) ? 
                    date('M d, Y', strtotime($request['start_date'] . ' +' . ($request['approved_days'] - 1) . ' days')) : 
                    date('M d, Y', strtotime($request['end_date']))) . '
            </td>
            <td class="py-4 px-4">
                <span class="inline-flex items-center justify-center w-8 h-8 bg-slate-700 rounded-full text-sm font-semibold text-white">
                    ' . (($request['status'] === 'approved' && $request['approved_days'] && $request['approved_days'] > 0) ? 
                        $request['approved_days'] : 
                        (new DateTime($request['start_date']))->diff(new DateTime($request['end_date']))->days + 1) . '
                </span>
            </td>
            <td class="py-4 px-4">
                <span class="text-slate-300 max-w-[150px] truncate block" 
                      title="' . htmlspecialchars($request['reason']) . '">
                    ' . htmlspecialchars($request['reason']) . '
                </span>
            </td>
            <td class="py-4 px-4">
                <div class="flex flex-col gap-1">
                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-' . ($request['dept_head_approval'] == 'approved' ? 'green' : ($request['dept_head_approval'] == 'rejected' ? 'red' : 'yellow')) . '-500/20 text-' . ($request['dept_head_approval'] == 'approved' ? 'green' : ($request['dept_head_approval'] == 'rejected' ? 'red' : 'yellow')) . '-400 border border-' . ($request['dept_head_approval'] == 'approved' ? 'green' : ($request['dept_head_approval'] == 'rejected' ? 'red' : 'yellow')) . '-500/30">
                        ' . ucfirst($request['dept_head_approval'] ?? 'pending') . '
                    </span>
                </div>
            </td>
            <td class="py-4 px-4">
                <div class="flex flex-col gap-1">
                    ' . (function() use ($request) {
                        $dept_status = $request['dept_head_approval'] ?? 'pending';
                        $director_status = $request['director_approval'] ?? 'pending';
                        
                        // If department head rejected, director status should show as rejected
                        if ($dept_status == 'rejected') {
                            $director_status = 'rejected';
                        }
                        
                        $director_color = $director_status == 'approved' ? 'green' : ($director_status == 'rejected' ? 'red' : 'yellow');
                        return '<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-' . $director_color . '-500/20 text-' . $director_color . '-400 border border-' . $director_color . '-500/30">
                            ' . ucfirst($director_status) . '
                        </span>';
                    })() . '
                </div>
            </td>
            <td class="py-4 px-4">
                <div class="flex items-center gap-2">
                    ' . $statusBadge . '
                    <button type="button" class="p-1 text-slate-400 hover:text-white transition-colors" 
                            onclick="showStatusInfo(' . $request['id'] . ')"
                            title="View Status Details">
                        <i class="fas fa-info-circle text-sm"></i>
                    </button>
                </div>
            </td>
            <td class="py-4 px-4">
                <div class="flex flex-col gap-2">
                    ' . (($request['dept_head_approval'] == 'approved' && $request['director_approval'] == 'approved') ? 
                        '<a href="print_leave_request.php?id=' . $request['id'] . '" target="_blank" class="inline-flex items-center px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-medium rounded-lg transition-colors">
                            <i class="fas fa-print mr-1"></i> Print
                        </a>' : 
                        (($request['dept_head_approval'] == 'rejected' || $request['director_approval'] == 'rejected') ? 
                            '<div class="text-center">
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-red-500/20 text-red-400 border border-red-500/30">Rejected</span>
                            </div>' : 
                            '<div class="text-center">
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-yellow-500/20 text-yellow-400 border border-yellow-500/30">Waiting</span>
                            </div>')) . '
                    
                    <button type="button" class="inline-flex items-center px-3 py-1 bg-primary hover:bg-primary/80 text-white text-xs font-medium rounded-lg transition-colors" 
                        onclick="viewRequestDetails(' . $request['id'] . ')"
                        title="View Details">
                        <i class="fas fa-eye mr-1"></i>View
                    </button>
                </div>
            </td>
        </tr>';
    }
    
    echo json_encode([
        'success' => true,
        'html' => $html,
        'count' => count($additional_requests),
        'hasMore' => count($additional_requests) >= $limit
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error fetching requests: ' . $e->getMessage()]);
}
?>
