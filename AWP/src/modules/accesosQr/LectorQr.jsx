import { useEffect, useRef, useState } from 'react';
import jsQR from 'jsqr';

export default function LectorQr({ onLeer }) {
  const [abierto, setAbierto] = useState(false);
  const [error, setError] = useState('');
  const video = useRef(null);
  const callback = useRef(onLeer);
  useEffect(() => { callback.current = onLeer; }, [onLeer]);

  useEffect(() => {
    if (!abierto) return;
    let activo = true;
    let stream;
    let timer;
    const canvas = document.createElement('canvas');
    const contexto = canvas.getContext('2d', { willReadFrequently: true });
    async function iniciar() {
      try {
        if (!navigator.mediaDevices?.getUserMedia) {
          throw new Error('La cámara necesita HTTPS o localhost y un navegador compatible. Puedes usar el token manual.');
        }
        stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' }, audio: false });
        if (!activo) { stream.getTracks().forEach((pista) => pista.stop()); return; }
        video.current.srcObject = stream;
        await video.current.play();
        timer = setInterval(() => {
          if (!activo || !video.current?.videoWidth) return;
          canvas.width = Math.min(video.current.videoWidth, 800);
          canvas.height = Math.round(video.current.videoHeight * canvas.width / video.current.videoWidth);
          contexto.drawImage(video.current, 0, 0, canvas.width, canvas.height);
          const pixeles = contexto.getImageData(0, 0, canvas.width, canvas.height);
          const codigo = jsQR(pixeles.data, pixeles.width, pixeles.height);
          if (codigo?.data) {
            // Se detiene al primer resultado: otro escaneo requiere abrir de nuevo.
            activo = false;
            clearInterval(timer);
            stream.getTracks().forEach((pista) => pista.stop());
            callback.current(codigo.data);
            setAbierto(false);
          }
        }, 250);
      } catch (e) {
        if (activo) { setError(e.message || 'No se pudo abrir la cámara.'); setAbierto(false); }
      }
    }
    iniciar();
    return () => { activo = false; clearInterval(timer); stream?.getTracks().forEach((pista) => pista.stop()); };
  }, [abierto]);

  return <div>
    <button type="button" className="hu01-boton-secundario" onClick={() => { setError(''); setAbierto(!abierto); }}>
      {abierto ? 'Cerrar cámara' : 'Escanear con cámara'}
    </button>
    {error && <p role="alert">{error}</p>}
    {abierto && <video ref={video} muted playsInline aria-label="Cámara de caseta" style={{ width: '100%', maxWidth: 480 }} />}
    {abierto && <p>Al leer el QR se detiene la cámara. Confirma el token para registrar entrada o salida.</p>}
  </div>;
}
