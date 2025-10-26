<?php
$compliance = $reportData['compliance_metrics'] ?? [];
$systemStats = $reportData['system_stats'] ?? [];
?>

<div class="space-y-6">
    <!-- Compliance Metrics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <div class="bg-gradient-to-r from-red-500 to-red-600 rounded-2xl p-6 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-red-100 text-sm">Late Submissions</p>
                    <p class="text-3xl font-bold"><?php echo $compliance['late_submissions'] ?? 0; ?></p>
                    <p class="text-red-100 text-xs mt-1"><?php echo $compliance['late_submission_rate'] ?? 0; ?>% rate</p>
                </div>
                <i class="fas fa-exclamation-triangle text-4xl opacity-75"></i>
            </div>
        </div>
        
        <div class="bg-gradient-to-r from-green-500 to-green-600 rounded-2xl p-6 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-green-100 text-sm">With Medical Cert</p>
                    <p class="text-3xl font-bold"><?php echo $compliance['with_medical_cert'] ?? 0; ?></p>
                    <p class="text-green-100 text-xs mt-1"><?php echo $compliance['medical_cert_rate'] ?? 0; ?>% rate</p>
                </div>
                <i class="fas fa-certificate text-4xl opacity-75"></i>
            </div>
        </div>
        
        <div class="bg-gradient-to-r from-orange-500 to-orange-600 rounded-2xl p-6 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-orange-100 text-sm">Appeals</p>
                    <p class="text-3xl font-bold"><?php echo $compliance['appeals'] ?? 0; ?></p>
                    <p class="text-orange-100 text-xs mt-1"><?php echo $compliance['appeal_rate'] ?? 0; ?>% rate</p>
                </div>
                <i class="fas fa-gavel text-4xl opacity-75"></i>
            </div>
        </div>
        
        <div class="bg-gradient-to-r from-blue-500 to-blue-600 rounded-2xl p-6 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-blue-100 text-sm">Avg Advance Notice</p>
                    <p class="text-3xl font-bold"><?php echo $compliance['avg_advance_notice_days'] ?? 0; ?></p>
                    <p class="text-blue-100 text-xs mt-1">days</p>
                </div>
                <i class="fas fa-calendar-plus text-4xl opacity-75"></i>
            </div>
        </div>
    </div>

    <!-- Compliance Analysis -->
    <div class="bg-slate-800 rounded-2xl border border-slate-700 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-700 bg-slate-700/30">
            <h3 class="text-xl font-semibold text-white flex items-center">
                <i class="fas fa-shield-alt text-orange-400 mr-3"></i>Compliance Analysis
            </h3>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <!-- Policy Adherence Chart -->
                <div>
                    <h4 class="text-lg font-semibold text-white mb-4">Policy Adherence Overview</h4>
                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <span class="text-slate-300">On-time Submissions</span>
                            <div class="flex items-center space-x-2">
                                <div class="w-32 bg-slate-700 rounded-full h-2">
                                    <div class="bg-green-500 h-2 rounded-full" style="width: <?php echo 100 - ($compliance['late_submission_rate'] ?? 0); ?>%"></div>
                                </div>
                                <span class="text-white font-semibold"><?php echo 100 - ($compliance['late_submission_rate'] ?? 0); ?>%</span>
                            </div>
                        </div>
                        
                        <div class="flex items-center justify-between">
                            <span class="text-slate-300">Medical Certificates</span>
                            <div class="flex items-center space-x-2">
                                <div class="w-32 bg-slate-700 rounded-full h-2">
                                    <div class="bg-blue-500 h-2 rounded-full" style="width: <?php echo $compliance['medical_cert_rate'] ?? 0; ?>%"></div>
                                </div>
                                <span class="text-white font-semibold"><?php echo $compliance['medical_cert_rate'] ?? 0; ?>%</span>
                            </div>
                        </div>
                        
                        <div class="flex items-center justify-between">
                            <span class="text-slate-300">Appeal Rate</span>
                            <div class="flex items-center space-x-2">
                                <div class="w-32 bg-slate-700 rounded-full h-2">
                                    <div class="bg-orange-500 h-2 rounded-full" style="width: <?php echo $compliance['appeal_rate'] ?? 0; ?>%"></div>
                                </div>
                                <span class="text-white font-semibold"><?php echo $compliance['appeal_rate'] ?? 0; ?>%</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Compliance Summary -->
                <div>
                    <h4 class="text-lg font-semibold text-white mb-4">Compliance Summary</h4>
                    <div class="space-y-3">
                        <div class="flex justify-between items-center p-3 bg-slate-700/50 rounded-lg">
                            <span class="text-slate-300">Total Requests</span>
                            <span class="text-white font-semibold"><?php echo $compliance['total_requests'] ?? 0; ?></span>
                        </div>
                        <div class="flex justify-between items-center p-3 bg-slate-700/50 rounded-lg">
                            <span class="text-slate-300">Late Submissions</span>
                            <span class="text-red-400 font-semibold"><?php echo $compliance['late_submissions'] ?? 0; ?></span>
                        </div>
                        <div class="flex justify-between items-center p-3 bg-slate-700/50 rounded-lg">
                            <span class="text-slate-300">With Medical Certificates</span>
                            <span class="text-green-400 font-semibold"><?php echo $compliance['with_medical_cert'] ?? 0; ?></span>
                        </div>
                        <div class="flex justify-between items-center p-3 bg-slate-700/50 rounded-lg">
                            <span class="text-slate-300">Appeals</span>
                            <span class="text-orange-400 font-semibold"><?php echo $compliance['appeals'] ?? 0; ?></span>
                        </div>
                        <div class="flex justify-between items-center p-3 bg-slate-700/50 rounded-lg">
                            <span class="text-slate-300">Average Advance Notice</span>
                            <span class="text-blue-400 font-semibold"><?php echo $compliance['avg_advance_notice_days'] ?? 0; ?> days</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recommendations -->
    <div class="bg-slate-800 rounded-2xl border border-slate-700 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-700 bg-slate-700/30">
            <h3 class="text-xl font-semibold text-white flex items-center">
                <i class="fas fa-lightbulb text-yellow-400 mr-3"></i>Compliance Recommendations
            </h3>
        </div>
        <div class="p-6">
            <div class="space-y-4">
                <?php if (($compliance['late_submission_rate'] ?? 0) > 20): ?>
                <div class="flex items-start space-x-3 p-4 bg-red-500/10 border border-red-500/20 rounded-lg">
                    <i class="fas fa-exclamation-triangle text-red-400 mt-1"></i>
                    <div>
                        <h5 class="font-semibold text-red-400">High Late Submission Rate</h5>
                        <p class="text-slate-300 text-sm">Consider implementing automated reminders and clearer submission deadlines.</p>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if (($compliance['medical_cert_rate'] ?? 0) < 50): ?>
                <div class="flex items-start space-x-3 p-4 bg-yellow-500/10 border border-yellow-500/20 rounded-lg">
                    <i class="fas fa-info-circle text-yellow-400 mt-1"></i>
                    <div>
                        <h5 class="font-semibold text-yellow-400">Low Medical Certificate Rate</h5>
                        <p class="text-slate-300 text-sm">Review sick leave policies and ensure proper documentation requirements are communicated.</p>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if (($compliance['avg_advance_notice_days'] ?? 0) < 3): ?>
                <div class="flex items-start space-x-3 p-4 bg-blue-500/10 border border-blue-500/20 rounded-lg">
                    <i class="fas fa-clock text-blue-400 mt-1"></i>
                    <div>
                        <h5 class="font-semibold text-blue-400">Short Advance Notice</h5>
                        <p class="text-slate-300 text-sm">Consider implementing minimum advance notice requirements for better planning.</p>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if (($compliance['appeal_rate'] ?? 0) > 10): ?>
                <div class="flex items-start space-x-3 p-4 bg-orange-500/10 border border-orange-500/20 rounded-lg">
                    <i class="fas fa-gavel text-orange-400 mt-1"></i>
                    <div>
                        <h5 class="font-semibold text-orange-400">High Appeal Rate</h5>
                        <p class="text-slate-300 text-sm">Review approval processes and ensure clear communication of rejection reasons.</p>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>











