
describe("ALKIRA - Registro de inmueble y asignación de inquilino", () => {
  const idPrueba = Date.now();
  const nombreInmueble = `Condominio Cypress ${idPrueba}`;
  const numeroUnidad = `CY-${idPrueba}`;

  const campo = (etiqueta) => {
    return cy
      .get('[role="dialog"] label.hu01-campo')
      .filter((index, elemento) => {
        const texto = elemento.querySelector("span")?.textContent?.trim();
        return texto === etiqueta;
      });
  };

  const comprobarRespuesta = (alias) => {
    cy.wait(alias).then(({ response }) => {
      expect(response, `Respuesta de ${alias}`).to.exist;
      expect(response.statusCode).to.be.within(200, 299);
    });
  };

  beforeEach(() => {
    cy.visit("http://localhost:5173/");
  });

  it("CP-01: Login, registro de inmueble, unidad y asignación", () => {

    // ==========================================
    // 1. INICIAR SESIÓN COMO ADMINISTRADOR
    // ==========================================

    cy.contains("h2", "Iniciar sesión")
      .should("be.visible");

    cy.intercept("POST", "/api/auth/login")
      .as("login");

    cy.env(["adminEmail", "adminPassword"]).then((env) => {
      expect(env.adminEmail, "Correo del administrador")
        .to.be.a("string")
        .and.not.be.empty;

      expect(env.adminPassword, "Contraseña del administrador")
        .to.be.a("string")
        .and.not.be.empty;

      cy.get("#correo")
        .should("be.visible")
        .type(env.adminEmail);

      cy.get("#contrasena")
        .should("be.visible")
        .type(env.adminPassword, { log: false });
    });

    cy.contains("button", "Ingresar").click();

    cy.wait("@login").then(({ response }) => {
      expect(response.statusCode).to.eq(200);

      expect(response.body.usuario).to.exist;

      expect(response.body.usuario.tipoUsuario)
        .to.eq("administrador");
    });

    cy.contains("button", "Propiedades")
      .should("be.visible");

    // ==========================================
    // 2. ENTRAR A PROPIEDADES
    // ==========================================

    cy.intercept("GET", "/api/inmuebles")
      .as("listarInmuebles");

    cy.contains("button", "Propiedades")
      .click();

    comprobarRespuesta("@listarInmuebles");

    cy.contains("h1", "Gestión de propiedades")
      .should("be.visible");

    const pestanas =
      'nav[aria-label="Secciones del módulo de propiedades"]';

    // ==========================================
    // 3. REGISTRAR INMUEBLE
    // ==========================================

    cy.get(pestanas)
      .contains("button", "Inmuebles")
      .click();

    cy.contains("button", "Nuevo inmueble")
      .click();

    cy.get('[role="dialog"]')
      .contains("h2", "Registrar inmueble")
      .should("be.visible");

    campo("Nombre *")
      .find("input")
      .type(nombreInmueble);

    campo("Tipo de inmueble")
      .find("select")
      .select("condominio");

    campo("Dirección *")
      .find("input")
      .type("Av. Pruebas Automatizadas 100");

    campo("Ciudad *")
      .find("input")
      .type("León");

    campo("Estado")
      .find("input")
      .type("Guanajuato");

    campo("Código postal")
      .find("input")
      .type("37000");

    campo("Descripción")
      .find("textarea")
      .type("Inmueble registrado mediante Cypress E2E");

    cy.intercept("POST", "/api/inmuebles")
      .as("crearInmueble");

    cy.contains("button", "Guardar inmueble")
      .click();

    cy.wait("@crearInmueble").then(({ request, response }) => {
      expect(response.statusCode).to.be.within(200, 299);

      expect(request.body.nombre)
        .to.eq(nombreInmueble);

      expect(request.body.tipo)
        .to.eq("condominio");

      expect(request.body.ciudad)
        .to.eq("León");
    });

    cy.contains("Inmueble registrado.")
      .should("be.visible");

    cy.contains("tr", nombreInmueble)
      .should("be.visible");

    // ==========================================
    // 4. REGISTRAR UNA UNIDAD
    // ==========================================

    cy.get(pestanas)
      .contains("button", "Unidades")
      .click();

    cy.contains("button", "Nueva unidad")
      .click();

    cy.get('[role="dialog"]')
      .contains("h2", "Registrar unidad")
      .should("be.visible");

    campo("Inmueble o torre *")
      .find("select")
      .select(nombreInmueble);

    campo("Número de unidad *")
      .find("input")
      .type(numeroUnidad);

    campo("Tipo de unidad")
      .find("select")
      .select("departamento");

    campo("Superficie (m²)")
      .find("input")
      .type("85.50");

    campo("Cajones de estacionamiento")
      .find("input")
      .clear()
      .type("1");

    cy.intercept(
      "POST",
      /\/api\/inmuebles\/\d+\/unidades$/
    ).as("crearUnidad");

    cy.contains("button", "Guardar unidad")
      .click();

    cy.wait("@crearUnidad").then(({ request, response }) => {
      expect(response.statusCode).to.be.within(200, 299);

      expect(request.body.numeroUnidad)
        .to.eq(numeroUnidad);

      expect(request.body.tipoUnidad)
        .to.eq("departamento");
    });

    cy.contains("Unidad registrada.")
      .should("be.visible");

    // ==========================================
    // 5. ABRIR ASIGNACIONES
    // ==========================================

    cy.get(pestanas)
      .contains("button", "Asignaciones")
      .click();

    cy.contains(
      "h2",
      "Asignaciones de propietarios e inquilinos"
    ).should("be.visible");

    cy.get(".hu01-asignacion-fila")
      .contains("strong", numeroUnidad)
      .closest(".hu01-asignacion-fila")
      .within(() => {
        cy.contains("button", "Asignar persona")
          .click();
      });

    // ==========================================
    // 6. VALIDAR EL FORMULARIO
    // ==========================================

    cy.get('[role="dialog"]')
      .contains("h2", "Asignar propietario o inquilino")
      .should("be.visible");

    campo("Relación")
      .find("select")
      .should("have.value", "inquilino");

    cy.get('[role="dialog"]')
      .contains("button", "Guardar asignación")
      .should("be.disabled");

    // ==========================================
    // 7. BUSCAR UN INQUILINO EXISTENTE
    // ==========================================

    cy.intercept("GET", "/api/usuarios/buscar*")
      .as("buscarUsuario");

    cy.env(["inquilinoBusqueda"]).then((env) => {
      expect(env.inquilinoBusqueda, "Usuario de prueba")
        .to.be.a("string")
        .and.not.be.empty;

      cy.get('[role="dialog"]')
        .find('input[placeholder="Ej. residente@alkira.test"]')
        .type(env.inquilinoBusqueda);
    });

    cy.get('[role="dialog"]')
      .contains("button", "Buscar")
      .click();

    cy.wait("@buscarUsuario").then(({ response }) => {
      expect(response.statusCode).to.eq(200);

      expect(response.body.usuarios)
        .to.be.an("array")
        .and.not.be.empty;
    });

    // Seleccionar el usuario encontrado

    cy.get('[role="dialog"] .hu01-resultados button')
      .first()
      .click();

    cy.get('[role="dialog"]')
      .contains("Seleccionado:")
      .should("be.visible");

    cy.get('[role="dialog"]')
      .contains("button", "Guardar asignación")
      .should("be.enabled");

    // ==========================================
    // 8. GUARDAR ASIGNACIÓN DEL INQUILINO
    // ==========================================

    cy.intercept(
      "POST",
      /\/api\/unidades\/\d+\/asignaciones$/
    ).as("guardarAsignacion");

    cy.get('[role="dialog"]')
      .contains("button", "Guardar asignación")
      .click();

    cy.wait("@guardarAsignacion").then(({ request, response }) => {
      expect(response.statusCode).to.be.within(200, 299);

      expect(request.body.idUsuario)
        .to.exist;

      expect(request.body.relacion)
        .to.eq("inquilino");

      expect(request.body.esPrincipal)
        .to.eq(true);

      expect(request.body.fechaInicio)
        .to.match(/^\d{4}-\d{2}-\d{2}$/);
    });

    cy.contains("Persona asignada a la unidad.")
      .should("be.visible");

    // ==========================================
    // 9. COMPROBAR PERSISTENCIA MEDIANTE API
    // ==========================================

    cy.request("GET", "/api/inmuebles")
      .then(({ status, body }) => {

        expect(status).to.eq(200);

        const inmueble = body.inmuebles.find(
          (item) => item.nombre === nombreInmueble
        );

        expect(inmueble, "Inmueble registrado")
          .to.exist;

        cy.request(
          "GET",
          `/api/inmuebles/${inmueble.idInmueble}/unidades`
        ).then(({ status, body: datos }) => {

          expect(status).to.eq(200);

          const unidad = datos.unidades.find(
            (item) => item.numeroUnidad === numeroUnidad
          );

          expect(unidad, "Unidad registrada")
            .to.exist;

          cy.request(
            "GET",
            `/api/unidades/${unidad.idUnidad}/asignaciones`
          ).then(({ status, body: datosAsignaciones }) => {

            expect(status).to.eq(200);

            const asignacion =
              datosAsignaciones.asignaciones.find(
                (item) => item.relacion === "inquilino"
              );

            expect(asignacion, "Inquilino asignado")
              .to.exist;

          });
        });
      });

  });
});
