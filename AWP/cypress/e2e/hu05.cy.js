
let password;

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
    cy.env(['TEST_BASE_URL', 'TEST_PASSWORD'], { log: false }).then((datos) => {
      expect(datos.TEST_BASE_URL, 'usar exclusivamente el entorno efímero').to.eq(Cypress.config('baseUrl'));
      password = datos.TEST_PASSWORD;
    });
  });

  it('HU05: registra con archivo, categoriza y consulta evidencia desde la web', () => {
    iniciar();
    cy.visit('/');
    cy.contains('nav button', 'Incidencias').click();
    cy.contains('button', 'Bandeja de incidencias').click();
    cy.contains('button', 'Registrar incidencia').click();
    campo('Residente que reporta', 'select').find('option').should('have.length', 2);
    campo('Título de la incidencia').type('QA navegador HU05');
    campo('Descripción detallada', 'textarea').type('Simulación web con evidencia real de prueba');
    const png = Cypress.Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aD1sAAAAASUVORK5CYII=', 'base64');
    cy.get('input[type=file]').selectFile({ contents: png, fileName: 'evidencia.png', mimeType: 'image/png' });
    cy.contains('button', 'Guardar incidencia').click();
    cy.get('[role=dialog]').should('not.exist');
    cy.contains('tr', 'QA navegador HU05').within(() => cy.contains('button', 'Categorizar').click());
    campo('Categoría técnica', 'select').select('electricidad');
    campo('Urgencia validada', 'select').select('alta');
    campo('Estado de la incidencia', 'select').select('asignada');
    cy.contains('button', 'Guardar cambios').click();
    cy.get('[role=dialog]').should('not.exist');
    cy.contains('tr', 'QA navegador HU05').should('contain', 'Asignada').within(() => {
      cy.contains('button', 'Evidencias (1)').click();
    });
    cy.contains('a', 'Ver evidencia').should('have.attr', 'href').then((ruta) => {
      cy.request(ruta).its('status').should('eq', 200);
    });
    cy.screenshot('hu05-evidencia-verificada');
    // Regresión visual: navegación compartida sigue disponible.
    cy.contains('nav button', 'Propiedades').click();
    cy.get('.hu01').should('exist');
  });

});
