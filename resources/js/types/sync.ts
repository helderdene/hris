/**
 * Type definitions for biometric device sync functionality.
 */

export type SyncStatus = 'pending' | 'syncing' | 'synced' | 'failed';

export interface EmployeeDeviceSync {
    id: number;
    employee_id: number;
    device_id: number;
    device_name?: string;
    employee_name?: string;
    status: SyncStatus;
    status_label: string;
    last_synced_at: string | null;
    last_attempted_at: string | null;
    retry_count: number;
    has_error: boolean;
    last_error?: string;
}

export interface DeviceSyncStatusMeta {
    total_employees: number;
    synced_count: number;
    pending_count: number;
    failed_count: number;
    device_id: number;
    device_name: string;
}

export interface EmployeeSyncStatusMeta {
    total_devices: number;
    synced_count: number;
    pending_count: number;
    failed_count: number;
    employee_id: number;
    employee_name: string;
}

export interface SyncStatusResponse {
    data: EmployeeDeviceSync[];
    meta: DeviceSyncStatusMeta | EmployeeSyncStatusMeta;
}

export interface SyncTriggerResponse {
    message: string;
    data: EmployeeDeviceSync[] | { queued: boolean; device_id: number };
}
