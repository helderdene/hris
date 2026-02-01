/**
 * Pay Type enum matching the backend PayType PHP enum.
 */
export enum PayType {
    Monthly = 'monthly',
    SemiMonthly = 'semi_monthly',
    Weekly = 'weekly',
    Daily = 'daily',
}

/**
 * Human-readable labels for pay types.
 */
export const PayTypeLabels: Record<PayType, string> = {
    [PayType.Monthly]: 'Monthly',
    [PayType.SemiMonthly]: 'Semi-Monthly',
    [PayType.Weekly]: 'Weekly',
    [PayType.Daily]: 'Daily',
};

/**
 * Bank Account Type enum matching the backend BankAccountType PHP enum.
 */
export enum BankAccountType {
    Savings = 'savings',
    Checking = 'checking',
}

/**
 * Human-readable labels for bank account types.
 */
export const BankAccountTypeLabels: Record<BankAccountType, string> = {
    [BankAccountType.Savings]: 'Savings',
    [BankAccountType.Checking]: 'Checking',
};

/**
 * Employee compensation record interface.
 * Matches the CompensationResource from the backend.
 */
export interface EmployeeCompensation {
    id: number;
    employee_id: number;
    basic_pay: string;
    currency: string;
    pay_type: PayType | null;
    pay_type_label: string | null;
    effective_date: string | null;
    bank_name: string | null;
    account_name: string | null;
    account_number: string | null;
    account_type: BankAccountType | null;
    account_type_label: string | null;
    created_at: string;
    updated_at: string;
}

/**
 * Compensation history record interface.
 * Matches the CompensationHistoryResource from the backend.
 */
export interface CompensationHistory {
    id: number;
    employee_id: number;
    previous_basic_pay: string | null;
    new_basic_pay: string;
    previous_pay_type: PayType | null;
    previous_pay_type_label: string | null;
    new_pay_type: PayType;
    new_pay_type_label: string;
    effective_date: string;
    remarks: string | null;
    changed_by: number | null;
    changed_by_name: string | null;
    ended_at: string | null;
    created_at: string;
    updated_at: string;
}

/**
 * Form data interface for creating/updating compensation.
 */
export interface CompensationFormData {
    basic_pay: string | number;
    pay_type: PayType | '';
    effective_date: string;
    remarks: string;
    bank_name: string;
    account_name: string;
    account_number: string;
    account_type: BankAccountType | '';
}

/**
 * Validation errors interface for the compensation form.
 */
export interface CompensationFormErrors {
    basic_pay?: string;
    pay_type?: string;
    effective_date?: string;
    remarks?: string;
    bank_name?: string;
    account_name?: string;
    account_number?: string;
    account_type?: string;
    general?: string;
}

/**
 * Props interface for CompensationTab component.
 */
export interface CompensationTabProps {
    employeeId: number;
    canManageEmployees: boolean;
}

/**
 * Props interface for CompensationEditModal component.
 */
export interface CompensationEditModalProps {
    employeeId: number;
    currentCompensation: EmployeeCompensation | null;
}

/**
 * Props interface for CompensationHistoryTimeline component.
 */
export interface CompensationHistoryTimelineProps {
    history: CompensationHistory[];
    loading?: boolean;
}

/**
 * API response interface for compensation endpoints.
 */
export interface CompensationApiResponse {
    data: {
        compensation: EmployeeCompensation | null;
        history: CompensationHistory[];
    };
}

/**
 * API response interface for store/update compensation endpoint.
 */
export interface CompensationStoreResponse {
    data: {
        compensation: EmployeeCompensation;
        history_entry: CompensationHistory;
    };
}
