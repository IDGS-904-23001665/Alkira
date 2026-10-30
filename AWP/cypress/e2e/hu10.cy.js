import QRCode from 'qrcode';

let password;
let hu05Presente;

// Se ejecuta contra la BD efímera de backend/pruebas/integracionHu05Hu10.php --web.
// No usar contra una base de trabajo ni proporcionar contraseñas reales.
function iniciar(rol = 'admin') {
  return cy.request({ method: 'POST', url: '/api/auth/login', log: false,
    body: { correo: `${rol}@alkira.test`, contrasena: password } });
}

function campo(texto, control = 'input') {
  return cy.contains('label', texto).find(control);
}

describe('HU05 y HU10 integradas sin móvil', () => {
  before(() => {
    cy.env(['TEST_BASE_URL', 'TEST_PASSWORD', 'HU05_PRESENT'], { log: false }).then((datos) => {
      expect(datos.TEST_BASE_URL, 'usar exclusivamente el entorno efímero').to.eq(Cypress.config('baseUrl'));
      password = datos.TEST_PASSWORD;
      hu05Presente = datos.HU05_PRESENT === true || datos.HU05_PRESENT === 'true';
    });
  });

  it('HU10: genera QR visible y completa entrada/salida con rechazo del tercer intento', () => {
    iniciar();
    cy.visit('/');
    cy.contains('nav button', 'Seguridad').click();
    cy.contains('button', 'Emitir pase QR').click();
    campo('Nombre completo del visitante').type('QA navegador visitante');
    cy.intercept('POST', '/api/accesos-qr').as('generar');
    cy.contains('button', 'Generar código QR').click();
    cy.wait('@generar').then(({ response }) => {
      const token = response.body.pase.tokenQr;
      cy.get('img[alt="Pase de acceso QR"]').should('have.attr', 'src').and('match', /^data:image\/png/);
      cy.get('[role=dialog]').find('button[aria-label=Cerrar]').click();
      cy.contains('button', 'Validar token QR').click();
      cy.get('[role=status]').should('contain', 'ENTRADA');
      campo('Token o código QR del visitante').type(token);
      cy.contains('button', 'Validar token QR').click();
      cy.get('[role=status]').should('contain', 'SALIDA');
      campo('Token o código QR del visitante').type(token);
      cy.contains('button', 'Validar token QR').click();
      cy.get('[role=status]').should('contain', 'RECHAZADO');
      cy.screenshot('hu10-ciclo-verificado');
    });
  });

  it('guardia inicia sesión desde formulario y solo tiene acceso a caseta', () => {
    cy.visit('/');
    cy.get('#correo').type('guardia@alkira.test');
    cy.get('#contrasena').type(password, { log: false });
    cy.contains('button', 'Ingresar').click();
    cy.contains('h1', 'Control de acceso QR').should('be.visible');
    cy.get('nav[aria-label="Navegación principal"] button').should('have.length', 1).and('contain', 'Seguridad');
    cy.contains('button', 'Emitir pase QR').should('not.be.visible');
    cy.request({ url: '/api/incidencias', failOnStatusCode: false }).its('status').should('eq', hu05Presente ? 403 : 404);
    cy.request({ method: 'POST', url: '/api/accesos-qr', body: {}, failOnStatusCode: false }).its('status').should('eq', 403);
  });

  it('lee un QR de video simulado y detiene la cámara antes de confirmar el movimiento', () => {
    let token;
    iniciar();
    cy.request('POST', '/api/accesos-qr', { idUsuarioAnfitrion: 3, nombreVisitante: 'QA cámara', horasVigencia: 2 })
      .then(({ body }) => { token = body.pase.tokenQr; return QRCode.toDataURL(token, { width: 400, margin: 4 }); })
      .then((imagen) => {
        iniciar('guardia');
        cy.visit('/', { onBeforeLoad(win) {
          cy.stub(win.navigator.mediaDevices, 'getUserMedia').callsFake(async () => {
            const canvas = win.document.createElement('canvas'); canvas.width = 400; canvas.height = 400;
            const img = new win.Image(); img.src = imagen;
            await new Promise((resolve) => { img.onload = resolve; });
            canvas.getContext('2d').drawImage(img, 0, 0);
            return canvas.captureStream(5);
          });
        } });
        cy.contains('button', 'Escanear con cámara').click();
        campo('Token o código QR del visitante').should('have.value', token);
        cy.get('video').should('not.exist');
        cy.contains('button', 'Validar token QR').click();
        cy.get('[role=status]').should('contain', 'ENTRADA');
      });
  });
});
