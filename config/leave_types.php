<?php
/**
 * Centralized Leave Types Configuration
 * Based on Civil Service Commission (CSC) Rules and Regulations
 * Updated to comply with official CSC leave entitlements
 */

function getLeaveTypes() {
    return [
    // Standard CSC Leave Types with Credits
    'vacation' => [
        'name' => 'Vacation Leave (VL)',
        'icon' => 'fas fa-umbrella-beach',
        'color' => 'bg-blue-500',
        'requires_credits' => true,
        'credit_field' => 'vacation_leave_balance',
        'description' => '15 days per year with full pay',
        'annual_credits' => 15,
        'cumulative' => true,
        'commutable' => true
    ],
    'sick' => [
        'name' => 'Sick Leave (SL)',
        'icon' => 'fas fa-thermometer-half',
        'color' => 'bg-red-500',
        'requires_credits' => true,
        'credit_field' => 'sick_leave_balance',
        'description' => '15 days per year with full pay',
        'annual_credits' => 15,
        'cumulative' => true,
        'commutable' => true,
        'requires_medical_certificate' => true
    ],
    'special_privilege' => [
        'name' => 'Special Leave Privilege (SLP)',
        'icon' => 'fas fa-star',
        'color' => 'bg-yellow-500',
        'requires_credits' => true,
        'credit_field' => 'special_leave_privilege_balance',
        'description' => '3 days per year, non-cumulative and non-commutable',
        'annual_credits' => 3,
        'cumulative' => false,
        'commutable' => false
    ],
    'maternity' => [
        'name' => 'Maternity Leave',
        'icon' => 'fas fa-baby',
        'color' => 'bg-pink-500',
        'requires_credits' => true,
        'credit_field' => 'maternity_leave_balance',
        'description' => '105 days with full pay, with option to extend for 30 days without pay',
        'annual_credits' => 105,
        'cumulative' => false,
        'commutable' => false,
        'gender_restricted' => 'female',
        'extension_available' => true,
        'extension_days' => 30
    ],
    'paternity' => [
        'name' => 'Paternity Leave',
        'icon' => 'fas fa-male',
        'color' => 'bg-cyan-500',
        'requires_credits' => true,
        'credit_field' => 'paternity_leave_balance',
        'description' => '7 working days for the first four deliveries of the legitimate spouse',
        'annual_credits' => 7,
        'cumulative' => false,
        'commutable' => false,
        'gender_restricted' => 'male',
        'delivery_limit' => 4
    ],
    'solo_parent' => [
        'name' => 'Solo Parent Leave',
        'icon' => 'fas fa-user-friends',
        'color' => 'bg-orange-500',
        'requires_credits' => true,
        'credit_field' => 'solo_parent_leave_balance',
        'description' => '7 working days per year',
        'annual_credits' => 7,
        'cumulative' => false,
        'commutable' => false
    ],
    'vawc' => [
        'name' => 'VAWC Leave',
        'icon' => 'fas fa-shield-alt',
        'color' => 'bg-red-600',
        'requires_credits' => true,
        'credit_field' => 'vawc_leave_balance',
        'description' => 'Violence Against Women and Their Children Leave - 10 days with full pay',
        'annual_credits' => 10,
        'cumulative' => false,
        'commutable' => false
    ],
    'rehabilitation' => [
        'name' => 'Rehabilitation Leave',
        'icon' => 'fas fa-heart',
        'color' => 'bg-green-500',
        'requires_credits' => true,
        'credit_field' => 'rehabilitation_leave_balance',
        'description' => 'Up to 6 months with pay, for job-related injuries or illnesses',
        'annual_credits' => 180, // 6 months
        'cumulative' => false,
        'commutable' => false,
        'requires_medical_certificate' => true
    ],
    'study' => [
        'name' => 'Study Leave',
        'icon' => 'fas fa-graduation-cap',
        'color' => 'bg-indigo-500',
        'requires_credits' => false,
        'credit_field' => null,
        'description' => 'Up to 6 months for qualified government employees pursuing studies',
        'annual_credits' => 0,
        'cumulative' => false,
        'commutable' => false,
        'without_pay' => true
    ],
    'terminal' => [
        'name' => 'Terminal Leave',
        'icon' => 'fas fa-sign-out-alt',
        'color' => 'bg-gray-600',
        'requires_credits' => true,
        'credit_field' => 'terminal_leave_balance',
        'description' => 'Accumulated Vacation and Sick Leave credits convertible to cash upon separation',
        'annual_credits' => 0,
        'cumulative' => true,
        'commutable' => true,
        'cash_convertible' => true
    ]
    ];
}
?>