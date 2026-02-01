/**
 * Assignment Type enum matching the backend AssignmentType PHP enum.
 */
export enum AssignmentType {
    Position = 'position',
    Department = 'department',
    Location = 'location',
    Supervisor = 'supervisor',
}

/**
 * Human-readable labels for assignment types.
 */
export const AssignmentTypeLabels: Record<AssignmentType, string> = {
    [AssignmentType.Position]: 'Position',
    [AssignmentType.Department]: 'Department',
    [AssignmentType.Location]: 'Work Location',
    [AssignmentType.Supervisor]: 'Supervisor',
};

/**
 * Assignment type as returned from the API with value and label.
 */
export interface AssignmentTypeResponse {
    value: AssignmentType;
    label: string;
}

/**
 * Employee assignment history record interface.
 * Matches the EmployeeAssignmentHistoryResource from the backend.
 */
export interface EmployeeAssignmentHistory {
    id: number;
    employee_id: number;
    assignment_type: AssignmentTypeResponse;
    previous_value_id: number | null;
    previous_value_name: string | null;
    new_value_id: number;
    new_value_name: string;
    effective_date: string;
    remarks: string | null;
    changed_by: number | null;
    changed_by_name: string | null;
    ended_at: string | null;
    created_at: string;
    updated_at: string;
}

/**
 * Form data interface for creating a new assignment change.
 */
export interface AssignmentChangeFormData {
    assignment_type: AssignmentType | '';
    new_value_id: number | '';
    effective_date: string;
    remarks: string;
}

/**
 * Dropdown option interface for assignment selects.
 */
export interface AssignmentDropdownOption {
    id: number;
    name?: string;
    title?: string;
    full_name?: string;
    employee_number?: string;
}

/**
 * Department dropdown option.
 */
export interface DepartmentOption {
    id: number;
    name: string;
}

/**
 * Position dropdown option.
 */
export interface PositionOption {
    id: number;
    title: string;
}

/**
 * Work location dropdown option.
 */
export interface WorkLocationOption {
    id: number;
    name: string;
}

/**
 * Supervisor (employee) dropdown option.
 */
export interface SupervisorOption {
    id: number;
    full_name: string;
    employee_number: string;
}

/**
 * Props interface for AssignmentChangeModal component.
 */
export interface AssignmentChangeModalProps {
    isOpen: boolean;
    employee: {
        id: number;
        department_id: number | null;
        position_id: number | null;
        work_location_id: number | null;
        supervisor_id: number | null;
    };
    departments: DepartmentOption[];
    positions: PositionOption[];
    workLocations: WorkLocationOption[];
    supervisorOptions: SupervisorOption[];
}

/**
 * Validation errors interface for the assignment form.
 */
export interface AssignmentFormErrors {
    assignment_type?: string;
    new_value_id?: string;
    effective_date?: string;
    remarks?: string;
    general?: string;
}
