import { useEffect, useRef, useState } from 'react';

const OUTPUT_SIZE = 512;
const MAX_AVATAR_BYTES = 2 * 1024 * 1024;

function clamp(value) {
    return Math.min(100, Math.max(0, value));
}

export default function AvatarCropDialog({ file, open, onClose, onApply }) {
    const dialogRef = useRef(null);
    const canvasRef = useRef(null);
    const dragRef = useRef(null);
    const zoomRef = useRef(null);
    const [image, setImage] = useState(null);
    const [position, setPosition] = useState({ x: 50, y: 50 });
    const [zoom, setZoom] = useState(1);
    const [error, setError] = useState('');
    const [processing, setProcessing] = useState(false);

    useEffect(() => {
        const dialog = dialogRef.current;

        if (open && !dialog?.open) {
            dialog.showModal();
            window.requestAnimationFrame(() => zoomRef.current?.focus());
        } else if (!open && dialog?.open) {
            dialog.close();
        }
    }, [open]);

    useEffect(() => {
        if (!file) {
            setImage(null);
            return undefined;
        }

        const url = URL.createObjectURL(file);
        const nextImage = new Image();
        setImage(null);
        setPosition({ x: 50, y: 50 });
        setZoom(1);
        setError('');
        nextImage.onload = () => setImage(nextImage);
        nextImage.onerror = () => setError('This image could not be opened. Please choose another photo.');
        nextImage.src = url;

        return () => {
            nextImage.onload = null;
            nextImage.onerror = null;
            URL.revokeObjectURL(url);
        };
    }, [file]);

    useEffect(() => {
        const canvas = canvasRef.current;
        if (!canvas || !image) return;

        const context = canvas.getContext('2d');
        if (!context) return;

        const cropSize = Math.min(image.naturalWidth, image.naturalHeight) / zoom;
        const left = (image.naturalWidth - cropSize) * position.x / 100;
        const top = (image.naturalHeight - cropSize) * position.y / 100;

        context.fillStyle = '#ffffff';
        context.fillRect(0, 0, OUTPUT_SIZE, OUTPUT_SIZE);
        context.drawImage(image, left, top, cropSize, cropSize, 0, 0, OUTPUT_SIZE, OUTPUT_SIZE);
    }, [image, position, zoom]);

    const startDrag = (event) => {
        if (!image) return;

        const cropSize = Math.min(image.naturalWidth, image.naturalHeight) / zoom;
        const width = event.currentTarget.clientWidth;
        dragRef.current = {
            x: event.clientX,
            y: event.clientY,
            position,
            overflowX: width * (image.naturalWidth / cropSize - 1),
            overflowY: width * (image.naturalHeight / cropSize - 1),
        };
        event.currentTarget.setPointerCapture(event.pointerId);
    };

    const moveDrag = (event) => {
        const drag = dragRef.current;
        if (!drag) return;

        setPosition({
            x: drag.overflowX > 0 ? clamp(drag.position.x - (event.clientX - drag.x) / drag.overflowX * 100) : 50,
            y: drag.overflowY > 0 ? clamp(drag.position.y - (event.clientY - drag.y) / drag.overflowY * 100) : 50,
        });
    };

    const applyCrop = () => {
        if (!canvasRef.current || !image || processing) return;

        setProcessing(true);
        canvasRef.current.toBlob((blob) => {
            setProcessing(false);

            if (!blob || blob.size > MAX_AVATAR_BYTES) {
                setError('The adjusted photo could not be saved under 2 MB. Please try another photo.');
                return;
            }

            onApply(new File([blob], 'profile-avatar.jpg', { type: 'image/jpeg' }));
        }, 'image/jpeg', 0.88);
    };

    return (
        <dialog
            ref={dialogRef}
            aria-labelledby="avatar-crop-title"
            className="m-auto max-h-[92vh] w-[min(460px,calc(100%_-_1.5rem))] overflow-y-auto rounded-xl border border-ink-950/10 bg-brand-cream p-0 text-ink-950 shadow-2xl backdrop:bg-ink-950/65"
            onCancel={(event) => { event.preventDefault(); if (!processing) onClose(); }}
            onClick={(event) => { if (!processing && event.target === event.currentTarget) onClose(); }}
        >
            <div className="flex items-start justify-between gap-4 border-b border-ink-950/10 px-5 py-4 sm:px-6">
                <div>
                    <h2 id="avatar-crop-title" className="text-lg font-bold">Adjust profile photo</h2>
                    <p className="mt-1 text-xs text-ink-950/55">Drag the photo or use the controls to choose what appears in your avatar.</p>
                </div>
                <button type="button" onClick={onClose} disabled={processing} aria-label="Close photo adjustment" className="grid size-8 shrink-0 place-items-center rounded-full text-xl text-ink-950/55 transition hover:bg-ink-950/10 hover:text-ink-950 disabled:opacity-40 focus-visible:outline-2 focus-visible:outline-brand-coral">×</button>
            </div>

            <div className="grid gap-5 px-5 py-5 sm:px-6">
                <div className="mx-auto aspect-square w-full max-w-72 overflow-hidden rounded-full border-4 border-white bg-ink-950 shadow-md">
                    <canvas
                        ref={canvasRef}
                        width={OUTPUT_SIZE}
                        height={OUTPUT_SIZE}
                        role="img"
                        aria-label="Profile photo crop preview"
                        onPointerDown={startDrag}
                        onPointerMove={moveDrag}
                        onPointerUp={() => { dragRef.current = null; }}
                        onPointerCancel={() => { dragRef.current = null; }}
                        className="size-full cursor-grab touch-none object-cover active:cursor-grabbing"
                    />
                </div>

                <div className="grid gap-3 text-xs font-semibold text-ink-950/75">
                    <label htmlFor="avatar-zoom" className="grid gap-1.5">Zoom <input ref={zoomRef} id="avatar-zoom" type="range" min="1" max="3" step="0.05" value={zoom} onChange={(event) => setZoom(Number(event.target.value))} className="w-full accent-brand-coral" /></label>
                    <div className="grid grid-cols-2 gap-4">
                        <label htmlFor="avatar-horizontal" className="grid gap-1.5">Horizontal <input id="avatar-horizontal" type="range" min="0" max="100" value={position.x} onChange={(event) => setPosition((current) => ({ ...current, x: Number(event.target.value) }))} className="w-full accent-brand-coral" /></label>
                        <label htmlFor="avatar-vertical" className="grid gap-1.5">Vertical <input id="avatar-vertical" type="range" min="0" max="100" value={position.y} onChange={(event) => setPosition((current) => ({ ...current, y: Number(event.target.value) }))} className="w-full accent-brand-coral" /></label>
                    </div>
                </div>
                {error && <p role="alert" className="text-xs font-semibold text-red-700">{error}</p>}
            </div>

            <div className="flex justify-end gap-3 border-t border-ink-950/10 px-5 py-4 sm:px-6">
                <button type="button" onClick={onClose} disabled={processing} className="rounded-md border border-ink-950/15 px-4 py-2 text-sm font-semibold transition hover:border-ink-950/40 disabled:opacity-40 focus-visible:outline-2 focus-visible:outline-brand-coral">Cancel</button>
                <button type="button" onClick={applyCrop} disabled={!image || processing} className="rounded-md bg-ink-950 px-4 py-2 text-sm font-semibold text-brand-cream transition hover:bg-brand-coral disabled:cursor-wait disabled:opacity-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-coral">{processing ? 'Applying…' : 'Use this photo'}</button>
            </div>
        </dialog>
    );
}
