<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Eraser } from 'lucide-vue-next';
import { onMounted, ref } from 'vue';

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();

defineProps<{
    modelValue?: string;
}>();

const canvasRef = ref<HTMLCanvasElement | null>(null);
const isDrawing = ref(false);
const hasSignature = ref(false);

let ctx: CanvasRenderingContext2D | null = null;

onMounted(() => {
    const canvas = canvasRef.value;
    if (!canvas) return;

    ctx = canvas.getContext('2d');
    if (!ctx) return;

    // Set canvas size
    const rect = canvas.getBoundingClientRect();
    canvas.width = rect.width * 2;
    canvas.height = rect.height * 2;
    ctx.scale(2, 2);

    ctx.strokeStyle = '#1f2937';
    ctx.lineWidth = 2;
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';
});

function getPosition(e: MouseEvent | TouchEvent): { x: number; y: number } {
    const canvas = canvasRef.value!;
    const rect = canvas.getBoundingClientRect();

    if ('touches' in e) {
        return {
            x: e.touches[0].clientX - rect.left,
            y: e.touches[0].clientY - rect.top,
        };
    }

    return {
        x: e.clientX - rect.left,
        y: e.clientY - rect.top,
    };
}

function startDrawing(e: MouseEvent | TouchEvent): void {
    if (!ctx) return;
    isDrawing.value = true;
    const { x, y } = getPosition(e);
    ctx.beginPath();
    ctx.moveTo(x, y);
}

function draw(e: MouseEvent | TouchEvent): void {
    if (!isDrawing.value || !ctx) return;
    e.preventDefault();
    const { x, y } = getPosition(e);
    ctx.lineTo(x, y);
    ctx.stroke();
    hasSignature.value = true;
}

function stopDrawing(): void {
    if (!ctx) return;
    isDrawing.value = false;
    ctx.closePath();

    if (hasSignature.value) {
        emit('update:modelValue', canvasRef.value!.toDataURL('image/png'));
    }
}

function clear(): void {
    if (!ctx || !canvasRef.value) return;
    const canvas = canvasRef.value;
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    hasSignature.value = false;
    emit('update:modelValue', '');
}

defineExpose({ clear });
</script>

<template>
    <div>
        <div class="relative rounded-md border border-input bg-background">
            <canvas
                ref="canvasRef"
                class="h-40 w-full cursor-crosshair touch-none"
                @mousedown="startDrawing"
                @mousemove="draw"
                @mouseup="stopDrawing"
                @mouseleave="stopDrawing"
                @touchstart="startDrawing"
                @touchmove="draw"
                @touchend="stopDrawing"
            />
            <div
                v-if="!hasSignature"
                class="pointer-events-none absolute inset-0 flex items-center justify-center text-sm text-muted-foreground"
            >
                Sign here
            </div>
        </div>
        <div class="mt-2 flex justify-end">
            <Button
                type="button"
                variant="ghost"
                size="sm"
                class="gap-1"
                :disabled="!hasSignature"
                @click="clear"
            >
                <Eraser class="h-3.5 w-3.5" />
                Clear
            </Button>
        </div>
    </div>
</template>
