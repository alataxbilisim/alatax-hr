import React, { useCallback, useEffect, useRef, useState } from 'react';
import jsQR from 'jsqr';
import { useTranslation } from '@shared/i18n';
import { portalApi } from '@shared/services/api';
import toast from 'react-hot-toast';
import { BsQrCodeScan, BsCheckCircle, BsXCircle } from 'react-icons/bs';

type ScanResult =
  | { ok: true; message: string; action: string; clock_time: string }
  | { ok: false; message: string }
  | null;

/**
 * Portal QR okuyucu — getUserMedia + jsQR (Apache-2.0 / MIT uyumlu).
 */
const PortalQrScanPage: React.FC = () => {
  const { t } = useTranslation('common');
  const videoRef = useRef<HTMLVideoElement | null>(null);
  const canvasRef = useRef<HTMLCanvasElement | null>(null);
  const streamRef = useRef<MediaStream | null>(null);
  const rafRef = useRef<number | null>(null);
  const scanningRef = useRef(true);
  const lastTokenRef = useRef<string | null>(null);

  const [cameraError, setCameraError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const [result, setResult] = useState<ScanResult>(null);

  const stopCamera = useCallback(() => {
    scanningRef.current = false;
    if (rafRef.current !== null) {
      cancelAnimationFrame(rafRef.current);
      rafRef.current = null;
    }
    streamRef.current?.getTracks().forEach((tr) => tr.stop());
    streamRef.current = null;
  }, []);

  const submitToken = useCallback(
    async (token: string) => {
      if (busy || lastTokenRef.current === token) return;
      lastTokenRef.current = token;
      setBusy(true);
      scanningRef.current = false;

      try {
        const res = await portalApi.timesheet.qrScan({ token });
        const data = (res.data as {
          data: { message: string; action: string; clock_time: string };
          message?: string;
        }).data;
        setResult({
          ok: true,
          message: data.message,
          action: data.action,
          clock_time: data.clock_time,
        });
        toast.success(data.message);
      } catch (err: unknown) {
        const ax = err as { response?: { data?: { message?: string } } };
        const msg = ax.response?.data?.message ?? t('pdks.scanFailed');
        setResult({ ok: false, message: msg });
        toast.error(msg);
        // Kısa süre sonra aynı token dışında yeniden tara
        setTimeout(() => {
          lastTokenRef.current = null;
          scanningRef.current = true;
        }, 2000);
      } finally {
        setBusy(false);
      }
    },
    [busy, t]
  );

  const tick = useCallback(() => {
    const video = videoRef.current;
    const canvas = canvasRef.current;
    if (!video || !canvas || !scanningRef.current) {
      rafRef.current = requestAnimationFrame(tick);
      return;
    }

    if (video.readyState === video.HAVE_ENOUGH_DATA) {
      const w = video.videoWidth;
      const h = video.videoHeight;
      canvas.width = w;
      canvas.height = h;
      const ctx = canvas.getContext('2d', { willReadFrequently: true });
      if (ctx) {
        ctx.drawImage(video, 0, 0, w, h);
        const imageData = ctx.getImageData(0, 0, w, h);
        const code = jsQR(imageData.data, w, h, { inversionAttempts: 'dontInvert' });
        if (code?.data) {
          void submitToken(code.data);
        }
      }
    }

    rafRef.current = requestAnimationFrame(tick);
  }, [submitToken]);

  useEffect(() => {
    let cancelled = false;

    void (async () => {
      if (!navigator.mediaDevices?.getUserMedia) {
        setCameraError(t('pdks.cameraUnsupported'));
        return;
      }
      try {
        const stream = await navigator.mediaDevices.getUserMedia({
          video: { facingMode: { ideal: 'environment' } },
          audio: false,
        });
        if (cancelled) {
          stream.getTracks().forEach((tr) => tr.stop());
          return;
        }
        streamRef.current = stream;
        if (videoRef.current) {
          videoRef.current.srcObject = stream;
          await videoRef.current.play();
        }
        scanningRef.current = true;
        rafRef.current = requestAnimationFrame(tick);
      } catch {
        setCameraError(t('pdks.cameraDenied'));
      }
    })();

    return () => {
      cancelled = true;
      stopCamera();
    };
  }, [stopCamera, t, tick]);

  const resetScan = () => {
    setResult(null);
    lastTokenRef.current = null;
    scanningRef.current = true;
  };

  return (
    <div style={{ padding: '1rem', maxWidth: 520, margin: '0 auto' }}>
      <h1 style={{ fontSize: 'var(--fs-xl)', display: 'flex', alignItems: 'center', gap: 8 }}>
        <BsQrCodeScan /> {t('pdks.scanTitle')}
      </h1>
      <p style={{ color: 'var(--text-secondary)' }}>{t('pdks.scanHint')}</p>

      {cameraError ? (
        <div
          role="alert"
          style={{
            padding: '1rem',
            borderRadius: 'var(--radius-sm)',
            background: 'var(--warning-bg, #fffbeb)',
            color: 'var(--text-primary)',
            border: '1px solid var(--warning, #f59e0b)',
          }}
        >
          {cameraError}
        </div>
      ) : (
        <div
          style={{
            position: 'relative',
            borderRadius: 'var(--radius-md)',
            overflow: 'hidden',
            background: '#000',
            aspectRatio: '3 / 4',
          }}
        >
          <video
            ref={videoRef}
            muted
            playsInline
            style={{ width: '100%', height: '100%', objectFit: 'cover' }}
          />
          <canvas ref={canvasRef} style={{ display: 'none' }} />
          {busy && (
            <div
              style={{
                position: 'absolute',
                inset: 0,
                background: 'rgba(0,0,0,0.45)',
                color: '#fff',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
              }}
            >
              {t('pdks.processing')}
            </div>
          )}
        </div>
      )}

      {result && (
        <div
          style={{
            marginTop: '1rem',
            padding: '1rem',
            borderRadius: 'var(--radius-sm)',
            border: `1px solid ${result.ok ? 'var(--success)' : 'var(--danger)'}`,
            display: 'flex',
            flexDirection: 'column',
            gap: '0.5rem',
          }}
        >
          <div style={{ display: 'flex', alignItems: 'center', gap: 8, fontWeight: 600 }}>
            {result.ok ? <BsCheckCircle color="var(--success)" /> : <BsXCircle color="var(--danger)" />}
            {result.message}
          </div>
          {result.ok && (
            <button type="button" className="btn btn-primary" onClick={resetScan}>
              {t('pdks.scanAgain')}
            </button>
          )}
          {!result.ok && (
            <button type="button" className="btn btn-secondary" onClick={resetScan}>
              {t('pdks.tryAgain')}
            </button>
          )}
        </div>
      )}
    </div>
  );
};

export default PortalQrScanPage;
