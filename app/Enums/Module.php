<?php

namespace App\Enums;

/**
 * Canonical module identifiers for subscription tier gating.
 */
enum Module: string
{
    // Starter (10 modules — always available on any paid plan)
    case HrManagement = 'hr_management';
    case OrganizationManagement = 'organization_management';
    case TimeAttendance = 'time_attendance';
    case BiometricIntegration = 'biometric_integration';
    case LeaveManagement = 'leave_management';
    case Payroll = 'payroll';
    case HrCompliance = 'hr_compliance';
    case EmployeeSelfService = 'employee_self_service';
    case UserAccessManagement = 'user_access_management';
    case VisitorManagement = 'visitor_management';

    // Professional (8 additional modules)
    case Recruitment = 'recruitment';
    case OnboardingPreboarding = 'onboarding_preboarding';
    case TrainingDevelopment = 'training_development';
    case PerformanceManagement = 'performance_management';
    case ProbationaryManagement = 'probationary_management';
    case ManagerSupervisor = 'manager_supervisor';
    case HelpCenter = 'help_center';
    case HrAnalytics = 'hr_analytics';

    // Enterprise (4 additional modules)
    case ComplianceTraining = 'compliance_training';
    case BackgroundCheckReference = 'background_check_reference';
    case AuditSecurity = 'audit_security';
    case CareersPortal = 'careers_portal';

    /**
     * Get a human-readable label for the module.
     */
    public function label(): string
    {
        return match ($this) {
            self::HrManagement => 'HR Management',
            self::OrganizationManagement => 'Organization Management',
            self::TimeAttendance => 'Time & Attendance',
            self::BiometricIntegration => 'Biometric Integration',
            self::LeaveManagement => 'Leave Management',
            self::Payroll => 'Payroll',
            self::HrCompliance => 'HR Compliance & Reporting',
            self::EmployeeSelfService => 'Employee Self-Service',
            self::UserAccessManagement => 'User & Access Management',
            self::VisitorManagement => 'Visitor Management',
            self::Recruitment => 'Recruitment',
            self::OnboardingPreboarding => 'Onboarding & Pre-boarding',
            self::TrainingDevelopment => 'Training & Development',
            self::PerformanceManagement => 'Performance Management',
            self::ProbationaryManagement => 'Probationary Management',
            self::ManagerSupervisor => 'Manager/Supervisor Module',
            self::HelpCenter => 'Help Center',
            self::HrAnalytics => 'HR Analytics',
            self::ComplianceTraining => 'Compliance Training',
            self::BackgroundCheckReference => 'Background Check & Reference',
            self::AuditSecurity => 'Audit & Security',
            self::CareersPortal => 'Careers/Public Portal',
        };
    }

    /**
     * Get all available module values as an array.
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Check if a given value is a valid module.
     */
    public static function isValid(string $value): bool
    {
        return in_array($value, self::values(), true);
    }

    /**
     * Try to create a module from a string value.
     */
    public static function tryFromValue(string $value): ?self
    {
        return self::tryFrom($value);
    }

    /**
     * Get the 9 modules included in the Starter tier.
     *
     * @return array<self>
     */
    public static function starterModules(): array
    {
        return [
            self::HrManagement,
            self::OrganizationManagement,
            self::TimeAttendance,
            self::BiometricIntegration,
            self::LeaveManagement,
            self::Payroll,
            self::HrCompliance,
            self::EmployeeSelfService,
            self::UserAccessManagement,
            self::VisitorManagement,
        ];
    }

    /**
     * Get the 17 modules included in the Professional tier (Starter + 8).
     *
     * @return array<self>
     */
    public static function professionalModules(): array
    {
        return [
            ...self::starterModules(),
            self::Recruitment,
            self::OnboardingPreboarding,
            self::TrainingDevelopment,
            self::PerformanceManagement,
            self::ProbationaryManagement,
            self::ManagerSupervisor,
            self::HelpCenter,
            self::HrAnalytics,
        ];
    }

    /**
     * Get all 21 modules included in the Enterprise tier.
     *
     * @return array<self>
     */
    public static function enterpriseModules(): array
    {
        return self::cases();
    }
}
