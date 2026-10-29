import { useEffect, useState } from 'react';
import QRCode from 'qrcode';

export default function ImagenQr({ token }) {
  const [imagen, setImagen] = useState('');
  useEffect(() => {
    let activo = true;
    QRCode.toDataURL(token, { width: 280, margin: 4, errorCorrectionLevel: 'M' })
      .then((url) => { if (activo) setImagen(url); })
      .catch(() => { if (activo) setImagen(''); });
    return () => { activo = false; };
  }, [token]);
  return imagen ? <div style={{ textAlign: 'center' }}>
    <img src={imagen} alt="Pase de acceso QR" width="280" height="280" />
    <p><a href={imagen} download="pase-alkira.png">Descargar QR</a></p>
  </div> : <p>Preparando imagen QR…</p>;
}
