# V4 Especificaciones Técnicas - Proceso de Adhesión

## 1. Llamada a la acción

Desde un bloque (diseño suministrado por el cliente) en principio en la home, maquetado con elementor. Acción que hace saltar a la calculadora.

Posible botón en el menú de (adhiérete o calculadora)

## 2. Identifícate

Creación de usuario o login con rol de (cliente / adherido) 

Se necesita:
• Empresa
• CIF (será el usuario)
• Nombre completo
• Email
• Teléfono
• Contraseña

Al registrarse recibe el cliente un email con sus credenciales

Cada CIF sólo puede tener un contrato en el sistema. Pero una persona con su email puede tener varios CIF.

Una vez logueado puede hacer cálculos o revisar su cuenta.

### 2.1. Mi cuenta
### 2.2. Mis datos
### 2.3. Contratos (Aquí se verá el estado, pendiente o finalizado)
### 2.5. Listado de cálculos de presupuesto
Con link a usuario, fecha y hora en el que lo tramitó.

## 3. Calcula tu presupuesto

Página solo para usuarios registrados.

Página de calculadora de precio totalmente gestionable desde el back office tanto materia prima como precio por tonelada. (se preparara 2 versiones adaptadas a cada una de las 2 webs)

Similar a la competencia https://www.genci.es/calculadora_genci

Una vez calculado el precio ese cálculo se guarda con relación a ese cliente en la base de datos para que o bien se pueda adherir con esos datos o un comercial pueda cerrar con él esa posible adhesión.

## 4. Completa los datos necesarios

En este paso habrá un formulario con la recogida de datos similar a el documento de recogida de datos.

**REINICIA**: Según formulario detallado en "2025 Ficha inscripción SCRAP Reinicia.docx".

**UBICA**: Según formulario detallado en "Ficha de inscripción a UBICA.pdf".

## 5. Realiza el pago

Aquí se procede a realizar el pago a través de tarjeta redsys o mediante transferencia. 

Si es por medio de transferencia debe dejar subir un justificante de pago.

Pese a subir el justificante quien realmente validará la realización de la transferencia será la empresa, que al marcar la casilla de cobrado debería saltar un email al cliente notificándole que puede volver a entrar y seguir con el proceso de adhesión.

## 6. Firma de documento

Con los datos recogidos en su cuenta y su cálculo de presupuesto se elabora de forma automática la documentación que debe de firmar.

Una vez creado ese documento a firmar con un botón de "firmar documentos" se procede a enviar los documentos y datos a docusign, avisar al cliente de que revise su correo. Y quedamos a la espera de la firma.

En este paso se firman usando el servicio https://www.docusign.com/ o solución alternativa si es efectiva y más económica.

## 7. Finalización tras la firma

Docusign nos avisará cuando se realice la firma, en ese momento recogeremos los documentos y el estado de la firma y marcaremos este proceso de este cliente como finalizado o su estado (puede fallar la firma) o no firmar, en este caso docusign nos lo comunica.

Finalizamos con mail a ambas partes, (firmante y administrador) con lo sucedido.

## Back office

### 1. El plugin puede ser instalado y desinstalado.

Se crean tablas nuevas en la base de datos para alojar toda la información requerida, configuraciones especiales, precios de calculadora, todas las necesarias etc…

### 2. Tendrá estas páginas de configuración

#### 2.1. Ajustes generales
Estos ajustes son los que requieran guardar variables, por ejemplo API Redsys, User, contraseña, etc…
API Docusign, etc…

#### 2.2. Gestión de documentos
Será posible crear documentos para la firma, se firmaran los que estén activos en cada momento de cualquier usuario en el proceso.

Se permitirá editar 3 partes (Header - Cuerpo - Footer) de cada documento. El Cuerpo ocupa tantas páginas A4 como sea necesario.

Las variables en ellos se pondrán como [nombre], [DNI], etc

Habrá un listado de variables usadas como ayuda para editar el documento

#### 2.3. Listado Usuarios 
Listado de usuarios.

#### 2.4. Listado Contratos de adhesión
Docusign una vez se firma nos devuelve el documento firmado. Lo almacenaremos en la web.

## Resumen de procesos de adhesión

**"Proceso de Adhesión"**

Desarrollo de un plugin a medida instalable para reutilizar, encargado de facilitar el proceso de adhesión y firma de contratos con sus clientes. Estos clientes deben de quedar registrados en wordpress, tendrán un espacio en su cuenta con toda la información de su cuenta y del proceso.

El administrador de la web podrá listar los usuarios, listados de cálculos de presupuestos realizados, acceso a todos los documentos y editar las diversas configuraciones que se detallan en este documento.

### Detallamos en pasos el proceso de adhesión

#### REINICIA

1. Desarrollo formulario de captación de datos de cliente nuevo
2. Creación de Lead en Odoo (Plugin)
3. Calculadora de precios de recogida de residuos (Kg y Unidades). A tener en cuenta importación puntual.
4. Pasarela de pago (tarjeta). Si transferencia se debe subir justificante y Reinicia tiene que validarlo.
5. Cumplimentación de SEPA sino es importación puntual y el cliente quiere
6. Generación, cumplimentación de contrato de alta
7. Generación, cumplimentación de formulario de baja + firma
8. Alta en EscalaApp (por parte de la empresa del ERP)

#### UBICA

1. Desarrollo formulario de captación de datos de cliente nuevo
2. Creación de Lead en Odoo (Plugin)
3. Calculadora de precios de recogida de residuos por tipología
4. Pasarela de pago (tarjeta). Si transferencia se debe subir justificante y Ubica tiene que validarlo.
5. Cumplimentación de SEPA si el cliente quiere.
6. Generación, cumplimentación y firma del contrato de alta.
7. Alta en EscalaApp (por parte de la empresa del ERP).

## Front office

(para las dos empresas)

## Notas extras

En cada web se podrá crear documentos diferentes.

El editor de documentos no va a ser como word o drive. Será un editor de texto enriquecido para poder cambiar el contenido de dichos documentos.

Es importante que se guarde cada cálculo de cada usuario.

Un usuario puede formalizar más de un contrato pero con CIFs diferentes, así que trataremos a esta entidad como si fuera un pedido tradicional. Pero cada uno diferenciado.

Si hacen un pedido nuevo tienen que cumplimentar todos los pasos desde el inicio como si fuese usuario nuevo.

La documentación que se firma es totalmente editable desde el sistema header, cuerpo y footer.

Necesitamos que ese documento esté dentro de un editor de texto enriquecido que podamos poner variables definidas tipo [usuario] [telefono] , y ese documento se pueda editar y ampliar en función de las condiciones necesarias del cliente.

## Preguntas a realizar al cliente o aclarar

¿Los documentos van separados?. Se puede unir en un único documento al final. Aunque hay casos en los que el SEPA igual no se firma y sin embargo el resto si ya que hay casos en los que el SEPA decide el cliente si lo firma o no.

Cada pedido son procedimientos diferentes, si un cliente vuelve a contratar dentro de X tiempo debe de rellenar de nuevo todos los datos.

## Instalación del plugin

El plugin creado se instalará en dos webs:
- Reinicia.es
- Ubicaenvases.es

Desde la web de https://escala360.es/ la empresa creará dos botones con un link a cada una de las webs anteriores donde se creará la llamada o invocación al plugin.

La empresa nos dirá el slogan y el punto exacto en cada una de las dos webs donde se invocará al plugin.

**Ubicaenvases.es**