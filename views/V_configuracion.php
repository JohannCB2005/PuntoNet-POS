<?php
// Configuración de la tienda. Solo Administrador: aquí se editan datos de la
// empresa, entorno SUNAT, credenciales y pagos. Los valores guardados se reflejan
// de inmediato en tickets/emisión vía config/settings.php (BD primero, .env fallback).
if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'Administrador') {
    echo "<h1>Acceso denegado</h1>";
    exit;
}
?>

<style>
    .config-tab-btn {
        border: 1px solid var(--gp-border);
        background: var(--gp-card);
        color: var(--gp-text-muted);
        font-size: 13px;
        font-weight: 600;
        border-radius: 10px;
        padding: 8px 14px;
        transition: all .15s ease;
    }
    .config-tab-btn:hover { border-color: var(--gp-primary); color: var(--gp-primary); }
    .config-tab-btn.active {
        background: var(--gp-primary);
        border-color: var(--gp-primary);
        color: #fff;
    }
    .config-pane { display: none; }
    .config-pane.active { display: block; }
    .config-section-title {
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: .04em;
        color: var(--gp-text-muted);
        font-weight: 700;
    }
    .config-file-preview {
        max-height: 60px;
        max-width: 120px;
        object-fit: contain;
        border-radius: 8px;
        border: 1px solid var(--gp-border);
        background: #fff;
        padding: 4px;
    }
    .qr-preview-wrap {
        width: 96px;
        height: 96px;
        flex-shrink: 0;
        border-radius: 12px;
        border: 1px solid var(--gp-border);
        background: #fff;
        padding: 4px;
        overflow: hidden;
    }
    .password-toggle-btn {
        border: 1px solid var(--gp-border);
        border-left: 0;
        border-radius: 0 8px 8px 0;
        background: #fff;
    }
</style>

<div class="container-fluid px-0 pb-4">
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between mb-4 gap-3">
        <div>
            <h4 class="mb-1 fw-bold text-dark">Configuración de la tienda</h4>
            <p class="text-muted mb-0" style="font-size: 14px;">
                Datos de la empresa, entorno SUNAT y pagos. Los cambios se aplican de inmediato.
            </p>
        </div>
        <div class="d-flex flex-column align-items-end gap-2">
            <button class="btn btn-light border rounded-circle d-flex align-items-center justify-content-center p-0" id="cerrar-configuracion" onclick="cerrarConfiguracion()" aria-label="Cerrar configuración" title="Cerrar" style="width:38px;height:38px;font-size:0;color:#6c757d;">
                <i class="bi bi-x-lg" style="font-size:16px;"></i>
            </button>
        </div>
    </div>

    <!-- Pestañas -->
    <div class="d-flex flex-wrap gap-2 mb-3" id="configTabs">
        <button class="config-tab-btn active" data-tab="empresa"><i class="bi bi-building me-1"></i> Datos de la Empresa</button>
        <button class="config-tab-btn" data-tab="sunat"><i class="bi bi-cloud-arrow-up me-1"></i> Entorno SUNAT</button>
        <button class="config-tab-btn" data-tab="cpe"><i class="bi bi-search me-1"></i> Consulta CPE</button>
        <button class="config-tab-btn" data-tab="guias"><i class="bi bi-truck me-1"></i> Guías</button>
        <button class="config-tab-btn" data-tab="sire"><i class="bi bi-receipt me-1"></i> SIRE</button>
        <button class="config-tab-btn" data-tab="qrapi"><i class="bi bi-qr-code me-1"></i> QR Api</button>
        <button class="config-tab-btn" data-tab="qztray"><i class="bi bi-printer me-1"></i> Qz Tray</button>
        <button class="config-tab-btn" data-tab="pse"><i class="bi bi-pencil-square me-1"></i> PSE</button>
        <button class="config-tab-btn" data-tab="pagos"><i class="bi bi-credit-card me-1"></i> Pagos</button>
        <button class="config-tab-btn" data-tab="marca"><i class="bi bi-palette me-1"></i> Marca</button>
        <button class="config-tab-btn" data-tab="integraciones"><i class="bi bi-plug me-1"></i> Integraciones</button>
    </div>

    <!-- Pestaña: Datos de la Empresa -->
    <div class="config-pane active" id="pane-empresa">
        <div class="card border-0 shadow-sm rounded-4 mb-3">
            <div class="card-body p-4">
                <h6 class="config-section-title mb-3">Identificación</h6>
                <div class="row g-3">
                    <div class="col-12 col-md-3"><label class="form-label fw-semibold text-muted" style="font-size:12px;">RUC</label><input class="form-control form-control-sm cfg-input" data-clave="SUNAT_RUC_EMISOR"></div>
                    <div class="col-12 col-md-5"><label class="form-label fw-semibold text-muted" style="font-size:12px;">Nombre (razón social)</label><input class="form-control form-control-sm cfg-input" data-clave="SUNAT_RAZON_SOCIAL"></div>
                    <div class="col-12 col-md-4"><label class="form-label fw-semibold text-muted" style="font-size:12px;">Nombre comercial</label><input class="form-control form-control-sm cfg-input" data-clave="SUNAT_NOMBRE_COMERCIAL"></div>
                    <div class="col-12 col-md-4"><label class="form-label fw-semibold text-muted" style="font-size:12px;">Título (nombre web)</label><input class="form-control form-control-sm cfg-input" data-clave="TITULO_WEB"></div>
                    <div class="col-6 col-md-3"><label class="form-label fw-semibold text-muted" style="font-size:12px;">Vencimiento de Certificado</label><input type="date" class="form-control form-control-sm cfg-input" data-clave="CERT_VENCIMIENTO"></div>
                    <div class="col-6 col-md-3"><label class="form-label fw-semibold text-muted" style="font-size:12px;">N° Cuenta de detracción</label><input class="form-control form-control-sm cfg-input" data-clave="CUENTA_DETRACCION"></div>
                    <div class="col-12 col-md-2"><label class="form-label fw-semibold text-muted" style="font-size:12px;">MTC</label><input class="form-control form-control-sm cfg-input" data-clave="EMPRESA_MTC"></div>
                </div>
            </div>
            <div class="card-footer bg-transparent border-0 pb-4 pt-0 d-flex justify-content-end">
                <button type="button" class="btn btn-sm btn-guardar-seccion gp-btn-primary border-0 rounded-3 px-3 fw-semibold" style="font-size:12px;"><i class="bi bi-check2-circle me-1"></i> Guardar</button>
            </div>
        </div>

        <div class="card border-0 shadow-sm rounded-4 mb-3">
            <div class="card-body p-4">
                <h6 class="config-section-title mb-3">Logo y Marca</h6>
                <div class="row g-4">
                    <div class="col-6 col-md-3" data-file-clave="LOGO_CLARO">
                        <label class="form-label fw-semibold text-muted" style="font-size:12px;">Logo (modo claro)</label>
                        <div class="mb-2"><img class="config-file-preview" data-preview="LOGO_CLARO" alt="Logo claro" style="display:none;"></div>
                        <input type="file" class="form-control form-control-sm" data-archivo="LOGO_CLARO" accept="image/*">
                    </div>
                    <div class="col-6 col-md-3" data-file-clave="LOGO_OSCURO">
                        <label class="form-label fw-semibold text-muted" style="font-size:12px;">Logo (modo oscuro)</label>
                        <div class="mb-2"><img class="config-file-preview" data-preview="LOGO_OSCURO" alt="Logo oscuro" style="display:none;"></div>
                        <input type="file" class="form-control form-control-sm" data-archivo="LOGO_OSCURO" accept="image/*">
                    </div>
                    <div class="col-6 col-md-3" data-file-clave="LOGO_FAVICON">
                        <label class="form-label fw-semibold text-muted" style="font-size:12px;">Favicon (ícono web)</label>
                        <div class="mb-2"><img class="config-file-preview" data-preview="LOGO_FAVICON" alt="Favicon" style="display:none;"></div>
                        <input type="file" class="form-control form-control-sm" data-archivo="LOGO_FAVICON" accept="image/*">
                    </div>
                    <div class="col-6 col-md-3" data-file-clave="LOGO_APP">
                        <label class="form-label fw-semibold text-muted" style="font-size:12px;">Logo APP</label>
                        <div class="mb-2"><img class="config-file-preview" data-preview="LOGO_APP" alt="Logo APP" style="display:none;"></div>
                        <input type="file" class="form-control form-control-sm" data-archivo="LOGO_APP" accept="image/*">
                    </div>
                    <div class="col-12 col-md-6" data-file-clave="RUBRICA">
                        <label class="form-label fw-semibold text-muted" style="font-size:12px;">Rúbrica (firma digital) — recomendado 700x300</label>
                        <div class="mb-2"><img class="config-file-preview" data-preview="RUBRICA" alt="Rúbrica" style="display:none;"></div>
                        <input type="file" class="form-control form-control-sm" data-archivo="RUBRICA" accept="image/*">
                    </div>
                </div>
                <p class="text-muted mt-3 mb-0" style="font-size:11px;">Se recomiendan resoluciones 700x300 para logos y una imagen cuadrada transparente (PNG) para el favicon.</p>
            </div>
            <div class="card-footer bg-transparent border-0 pb-4 pt-0 d-flex justify-content-end">
                <button type="button" class="btn btn-sm btn-guardar-seccion gp-btn-primary border-0 rounded-3 px-3 fw-semibold" style="font-size:12px;"><i class="bi bi-check2-circle me-1"></i> Guardar</button>
            </div>
        </div>

        <div class="card border-0 shadow-sm rounded-4 mb-3">
            <div class="card-body p-4">
                <h6 class="config-section-title mb-3">Dirección Fiscal</h6>
                <div class="row g-3">
                    <div class="col-6 col-md-2"><label class="form-label fw-semibold text-muted" style="font-size:12px;">Ubigeo</label><input class="form-control form-control-sm cfg-input" data-clave="SUNAT_UBIGEO"></div>
                    <div class="col-6 col-md-2"><label class="form-label fw-semibold text-muted" style="font-size:12px;">Departamento</label><input class="form-control form-control-sm cfg-input" data-clave="SUNAT_DEPARTAMENTO"></div>
                    <div class="col-6 col-md-2"><label class="form-label fw-semibold text-muted" style="font-size:12px;">Provincia</label><input class="form-control form-control-sm cfg-input" data-clave="SUNAT_PROVINCIA"></div>
                    <div class="col-6 col-md-2"><label class="form-label fw-semibold text-muted" style="font-size:12px;">Distrito</label><input class="form-control form-control-sm cfg-input" data-clave="SUNAT_DISTRITO"></div>
                    <div class="col-6 col-md-2"><label class="form-label fw-semibold text-muted" style="font-size:12px;">Email</label><input class="form-control form-control-sm cfg-input" data-clave="SUNAT_EMAIL"></div>
                    <div class="col-6 col-md-2"><label class="form-label fw-semibold text-muted" style="font-size:12px;">Teléfono</label><input class="form-control form-control-sm cfg-input" data-clave="SUNAT_TELEFONO"></div>
                    <div class="col-12"><label class="form-label fw-semibold text-muted" style="font-size:12px;">Dirección</label><input class="form-control form-control-sm cfg-input" data-clave="SUNAT_DIRECCION"></div>
                    <div class="col-12"><label class="form-label fw-semibold text-muted" style="font-size:12px;">URL consulta de comprobante</label><input class="form-control form-control-sm cfg-input" data-clave="SUNAT_CONSULTA_URL" placeholder="https://nissi.likadev.com"></div>
                </div>
            </div>
            <div class="card-footer bg-transparent border-0 pb-4 pt-0 d-flex justify-content-end">
                <button type="button" class="btn btn-sm btn-guardar-seccion gp-btn-primary border-0 rounded-3 px-3 fw-semibold" style="font-size:12px;"><i class="bi bi-check2-circle me-1"></i> Guardar</button>
            </div>
        </div>
    </div>

    <!-- Pestaña: Entorno SUNAT -->
    <div class="config-pane" id="pane-sunat">
        <div class="card border-0 shadow-sm rounded-4 mb-3">
            <div class="card-body p-4">
                <h6 class="config-section-title mb-3">Entorno del sistema</h6>
                <div class="alert alert-info py-2" style="font-size:12px;">
                    El sistema está en <strong>BETA</strong> (RUC de pruebas de SUNAT). Al cambiar a <strong>PRODUCCION</strong> se emitirán comprobantes reales; asegúrate de que el certificado y las credenciales sean los correctos.
                </div>
                <div class="row g-3">
                    <div class="col-6 col-md-3">
                        <label class="form-label fw-semibold text-muted" style="font-size:12px;">SOAP Tipo</label>
                        <select class="form-select form-select-sm cfg-input" data-clave="SUNAT_MODO">
                            <option value="BETA">BETA</option>
                            <option value="PRODUCCION">PRODUCCIÓN</option>
                        </select>
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label fw-semibold text-muted" style="font-size:12px;">SOAP Envío</label>
                        <select class="form-select form-select-sm cfg-input" data-clave="SOAP_ENVIO">
                            <option value="SUNAT">Sunat</option>
                            <option value="OSE">OSE</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label fw-semibold text-muted" style="font-size:12px;">Usuario Secundario Sunat/OSE (RUC + Usuario)</label>
                        <input class="form-control form-control-sm cfg-input" data-clave="SUNAT_SOL_USUARIO" placeholder="10738870960USUARIO1">
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label fw-semibold text-muted" style="font-size:12px;">SOAP Password</label>
                        <div class="input-group input-group-sm">
                            <input type="password" class="form-control cfg-input" data-clave="SUNAT_SOL_CLAVE" autocomplete="new-password">
                            <button class="btn btn-outline-secondary password-toggle-btn" type="button" title="Mostrar/ocultar"><i class="bi bi-eye"></i></button>
                        </div>
                    </div>
                    <div class="col-12 col-md-6" data-file-clave="SUNAT_CERT_PATH">
                        <label class="form-label fw-semibold text-muted" style="font-size:12px;">Certificado (.pem)</label>
                        <input type="file" class="form-control form-control-sm" data-archivo="SUNAT_CERT_PATH" accept=".pem,.crt,.cer">
                        <small class="text-muted d-block mt-1 cfg-file-current" data-current="SUNAT_CERT_PATH" style="font-size:11px;"></small>
                    </div>
                </div>
            </div>
            <div class="card-footer bg-transparent border-0 pb-4 pt-0 d-flex justify-content-end">
                <button type="button" class="btn btn-sm btn-guardar-seccion gp-btn-primary border-0 rounded-3 px-3 fw-semibold" style="font-size:12px;"><i class="bi bi-check2-circle me-1"></i> Guardar</button>
            </div>
        </div>
    </div>

    <!-- Pestaña: Consulta CPE -->
    <div class="config-pane" id="pane-cpe">
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body p-4">
                <h6 class="config-section-title mb-3">Consulta integrada de CPE — Validador de documentos</h6>
                <div class="row g-3">
                    <div class="col-12 col-md-6"><label class="form-label fw-semibold text-muted" style="font-size:12px;">Client ID</label><input class="form-control form-control-sm cfg-input" data-clave="CPE_CLIENT_ID"></div>
                    <div class="col-12 col-md-6"><label class="form-label fw-semibold text-muted" style="font-size:12px;">Client Secret (Clave)</label><input type="password" class="form-control form-control-sm cfg-input" data-clave="CPE_CLIENT_SECRET" autocomplete="new-password"></div>
                </div>
            </div>
            <div class="card-footer bg-transparent border-0 pb-4 pt-0 d-flex justify-content-end">
                <button type="button" class="btn btn-sm btn-guardar-seccion gp-btn-primary border-0 rounded-3 px-3 fw-semibold" style="font-size:12px;"><i class="bi bi-check2-circle me-1"></i> Guardar</button>
            </div>
        </div>
    </div>

    <!-- Pestaña: Guías electrónicas -->
    <div class="config-pane" id="pane-guias">
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body p-4">
                <h6 class="config-section-title mb-3">Guías electrónicas</h6>
                <div class="row g-3">
                    <div class="col-12 col-md-6"><label class="form-label fw-semibold text-muted" style="font-size:12px;">SOAP Usuario</label><input class="form-control form-control-sm cfg-input" data-clave="GUIA_SOAP_USUARIO"></div>
                    <div class="col-12 col-md-6"><label class="form-label fw-semibold text-muted" style="font-size:12px;">SOAP Password</label><input type="password" class="form-control form-control-sm cfg-input" data-clave="GUIA_SOAP_PASSWORD" autocomplete="new-password"></div>
                    <div class="col-12 col-md-6"><label class="form-label fw-semibold text-muted" style="font-size:12px;">Client ID</label><input class="form-control form-control-sm cfg-input" data-clave="GUIA_CLIENT_ID"></div>
                    <div class="col-12 col-md-6"><label class="form-label fw-semibold text-muted" style="font-size:12px;">Client Secret (Clave)</label><input type="password" class="form-control form-control-sm cfg-input" data-clave="GUIA_CLIENT_SECRET" autocomplete="new-password"></div>
                </div>
            </div>
            <div class="card-footer bg-transparent border-0 pb-4 pt-0 d-flex justify-content-end">
                <button type="button" class="btn btn-sm btn-guardar-seccion gp-btn-primary border-0 rounded-3 px-3 fw-semibold" style="font-size:12px;"><i class="bi bi-check2-circle me-1"></i> Guardar</button>
            </div>
        </div>
    </div>

    <!-- Pestaña: SIRE -->
    <div class="config-pane" id="pane-sire">
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body p-4">
                <h6 class="config-section-title mb-3">SIRE</h6>
                <div class="row g-3">
                    <div class="col-12 col-md-6"><label class="form-label fw-semibold text-muted" style="font-size:12px;">Client ID</label><input class="form-control form-control-sm cfg-input" data-clave="SIRE_CLIENT_ID"></div>
                    <div class="col-12 col-md-6"><label class="form-label fw-semibold text-muted" style="font-size:12px;">Client Secret (Clave)</label><input type="password" class="form-control form-control-sm cfg-input" data-clave="SIRE_CLIENT_SECRET" autocomplete="new-password"></div>
                    <div class="col-12 col-md-6"><label class="form-label fw-semibold text-muted" style="font-size:12px;">Usuario</label><input class="form-control form-control-sm cfg-input" data-clave="SIRE_USUARIO"></div>
                    <div class="col-12 col-md-6"><label class="form-label fw-semibold text-muted" style="font-size:12px;">Contraseña</label><input type="password" class="form-control form-control-sm cfg-input" data-clave="SIRE_CONTRASENA" autocomplete="new-password"></div>
                </div>
            </div>
            <div class="card-footer bg-transparent border-0 pb-4 pt-0 d-flex justify-content-end">
                <button type="button" class="btn btn-sm btn-guardar-seccion gp-btn-primary border-0 rounded-3 px-3 fw-semibold" style="font-size:12px;"><i class="bi bi-check2-circle me-1"></i> Guardar</button>
            </div>
        </div>
    </div>

    <!-- Pestaña: QR Api -->
    <div class="config-pane" id="pane-qrapi">
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body p-4">
                <h6 class="config-section-title mb-3">Envío de mensajes a través de QR Api</h6>
                <p class="text-muted" style="font-size:12px;">Esta función tiene dos formas de enviar sus comprobantes: a través de Chat Búho o del Servicio de WhatsApp Web.</p>
                <div class="d-flex gap-3">
                    <div class="form-check form-check-inline"><input class="form-check-input cfg-sino" type="radio" name="qrRadio" data-clave="QRAPI_HABILITADO" value="SI" id="qrSi"><label class="form-check-label" for="qrSi">Si</label></div>
                    <div class="form-check form-check-inline"><input class="form-check-input cfg-sino" type="radio" name="qrRadio" data-clave="QRAPI_HABILITADO" value="NO" id="qrNo"><label class="form-check-label" for="qrNo">No</label></div>
                </div>
            </div>
            <div class="card-footer bg-transparent border-0 pb-4 pt-0 d-flex justify-content-end">
                <button type="button" class="btn btn-sm btn-guardar-seccion gp-btn-primary border-0 rounded-3 px-3 fw-semibold" style="font-size:12px;"><i class="bi bi-check2-circle me-1"></i> Guardar</button>
            </div>
        </div>
    </div>

    <!-- Pestaña: Qz Tray -->
    <div class="config-pane" id="pane-qztray">
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body p-4">
                <h6 class="config-section-title mb-3">Certificado Qz Tray</h6>
                <p class="text-muted" style="font-size:12px;">Se tiene que ingresar los dos archivos generados en los certificados de Qz Tray (es importante colocar los dos).</p>
                <div class="row g-3">
                    <div class="col-12 col-md-6" data-file-clave="QZTRAY_DIGITAL_CERT">
                        <label class="form-label fw-semibold text-muted" style="font-size:12px;">Digital Certificate</label>
                        <input type="file" class="form-control form-control-sm" data-archivo="QZTRAY_DIGITAL_CERT" accept=".pem,.crt,.cer">
                        <small class="text-muted d-block mt-1 cfg-file-current" data-current="QZTRAY_DIGITAL_CERT" style="font-size:11px;"></small>
                    </div>
                    <div class="col-12 col-md-6" data-file-clave="QZTRAY_PRIVATE_KEY">
                        <label class="form-label fw-semibold text-muted" style="font-size:12px;">Private Key</label>
                        <input type="file" class="form-control form-control-sm" data-archivo="QZTRAY_PRIVATE_KEY" accept=".key,.pem">
                        <small class="text-muted d-block mt-1 cfg-file-current" data-current="QZTRAY_PRIVATE_KEY" style="font-size:11px;"></small>
                    </div>
                </div>
            </div>
            <div class="card-footer bg-transparent border-0 pb-4 pt-0 d-flex justify-content-end">
                <button type="button" class="btn btn-sm btn-guardar-seccion gp-btn-primary border-0 rounded-3 px-3 fw-semibold" style="font-size:12px;"><i class="bi bi-check2-circle me-1"></i> Guardar</button>
            </div>
        </div>
    </div>

    <!-- Pestaña: PSE -->
    <div class="config-pane" id="pane-pse">
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body p-4">
                <h6 class="config-section-title mb-3">Servicio PSE</h6>
                <div class="d-flex gap-3">
                    <div class="form-check form-check-inline"><input class="form-check-input cfg-sino" type="radio" name="pseRadio" data-clave="PSE_HABILITADO" value="SI" id="pseSi"><label class="form-check-label" for="pseSi">Si</label></div>
                    <div class="form-check form-check-inline"><input class="form-check-input cfg-sino" type="radio" name="pseRadio" data-clave="PSE_HABILITADO" value="NO" id="pseNo"><label class="form-check-label" for="pseNo">No</label></div>
                </div>
            </div>
            <div class="card-footer bg-transparent border-0 pb-4 pt-0 d-flex justify-content-end">
                <button type="button" class="btn btn-sm btn-guardar-seccion gp-btn-primary border-0 rounded-3 px-3 fw-semibold" style="font-size:12px;"><i class="bi bi-check2-circle me-1"></i> Guardar</button>
            </div>
        </div>
    </div>

    <!-- Pestaña: Pagos -->
    <div class="config-pane" id="pane-pagos">
        <!-- Cobro por verificación manual (sin pasarela, sin comisiones) -->
        <?php $bancosManual = [
            ['clave' => 'BCP', 'nombre' => 'BCP'],
            ['clave' => 'BBVA', 'nombre' => 'BBVA'],
            ['clave' => 'INTERBANK', 'nombre' => 'Interbank'],
            ['clave' => 'SCOTIABANK', 'nombre' => 'Scotiabank'],
        ]; ?>
        <div class="card border-0 shadow-sm rounded-4 mb-3">
            <div class="card-body p-4">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <div>
                        <h6 class="config-section-title mb-1">Cobro por verificación manual</h6>
                        <p class="text-muted mb-0" style="font-size:11px;">
                            Cobra sin pasarela: el cliente paga con tu QR (Yape/Plin/Izipay QR) o por transferencia
                            bancaria y tú apruebas el pago manualmente desde <strong>Pedidos Online</strong>. Evita las comisiones de las pasarelas.
                        </p>
                    </div>
                    <div class="form-check form-switch ms-3">
                        <input class="form-check-input cfg-switch" type="checkbox" data-clave="PAGO_MANUAL_HABILITADO" id="pagoManualSwitch">
                    </div>
                </div>

                <div class="row g-4 mb-4">
                    <div class="col-6 col-md-3">
                        <label class="form-label fw-semibold text-muted" style="font-size:12px;">Minutos de reserva</label>
                        <input class="form-control form-control-sm cfg-input" data-clave="PAGO_MANUAL_MINUTOS" placeholder="60">
                    </div>
                </div>

                <!-- Billetera (QR) -->
                <div class="border rounded-3 p-3 mb-4">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div>
                            <div class="fw-bold" style="font-size:14px;">Billetera — QR Yape / Plin / Izipay QR</div>
                            <small class="text-muted" style="font-size:11px;">Cada billetera se activa con su propio interruptor. Para Yape y Plin, <strong>carga la imagen del QR y se decodifica sola</strong>: el código se redibuja automáticamente en el checkout (no hace falta copiar el contenido a mano).</small>
                        </div>
                        <div class="alert alert-warning py-2 mb-3" style="font-size:12px;">
                            <i class="bi bi-exclamation-triangle me-1"></i>
                            Los datos que coloques aquí (contenido del QR y titular) son <strong>bajo tu responsabilidad</strong>: asegúrate de que el QR corresponda a tu cuenta y no muestres el QR de otra persona.
                        </div>
                        <div class="form-check form-switch">
                            <input class="form-check-input cfg-switch" type="checkbox" data-clave="PAGO_MANUAL_BILLETERA_HABILITADO" id="billeteraManualSwitch">
                        </div>
                    </div>
                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <div class="bg-light rounded-3 p-3 h-100">
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <div class="fw-bold" style="font-size:13px;color:#23284E;">Yape</div>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input cfg-switch" type="checkbox" data-clave="PAGO_MANUAL_BILLETERA_YAPE_HABILITADO">
                                    </div>
                                </div>
                                <div class="mb-2">
                                    <label class="form-label fw-semibold text-muted mb-0" style="font-size:11px;">Cargar imagen del QR (se decodifica sola)</label>
                                    <input type="file" class="form-control form-control-sm qr-auto-decodificar" data-target="PAGO_MANUAL_QR_YAPE_CONTENIDO" data-preview="qrPreviewYape" accept="image/*">
                                </div>
                                <div class="d-flex align-items-start gap-2">
                                    <div class="qr-preview-wrap" id="qrPreviewYape" style="display:none;"></div>
                                    <textarea class="form-control form-control-sm cfg-input font-monospace" data-clave="PAGO_MANUAL_QR_YAPE_CONTENIDO" data-preview="qrPreviewYape" rows="3" placeholder="Contenido EMVCo (se llena solo al subir la imagen)"></textarea>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-md-6">
                            <div class="bg-light rounded-3 p-3 h-100">
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <div class="fw-bold" style="font-size:13px;color:#23284E;">Plin</div>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input cfg-switch" type="checkbox" data-clave="PAGO_MANUAL_BILLETERA_PLIN_HABILITADO">
                                    </div>
                                </div>
                                <div class="mb-2">
                                    <label class="form-label fw-semibold text-muted mb-0" style="font-size:11px;">Cargar imagen del QR (se decodifica sola)</label>
                                    <input type="file" class="form-control form-control-sm qr-auto-decodificar" data-target="PAGO_MANUAL_QR_PLIN_CONTENIDO" data-preview="qrPreviewPlin" accept="image/*">
                                </div>
                                <div class="d-flex align-items-start gap-2">
                                    <div class="qr-preview-wrap" id="qrPreviewPlin" style="display:none;"></div>
                                    <textarea class="form-control form-control-sm cfg-input font-monospace" data-clave="PAGO_MANUAL_QR_PLIN_CONTENIDO" data-preview="qrPreviewPlin" rows="3" placeholder="Contenido EMVCo (se llena solo al subir la imagen)"></textarea>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-md-6">
                            <div class="bg-light rounded-3 p-3 h-100">
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <div class="fw-bold" style="font-size:13px;color:#23284E;">Izipay QR</div>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input cfg-switch" type="checkbox" data-clave="PAGO_MANUAL_BILLETERA_IZIPAY_HABILITADO">
                                    </div>
                                </div>
                                <label class="form-label fw-semibold text-muted mb-0" style="font-size:11px;">Imagen del QR Izipay (opcional)</label>
                                <input type="file" class="form-control form-control-sm" data-archivo="PAGO_MANUAL_BILLETERA_QR" accept="image/*">
                                <img class="config-file-preview mt-2" data-preview="PAGO_MANUAL_BILLETERA_QR" style="display:none;" alt="Vista previa QR">
                            </div>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold text-muted" style="font-size:12px;">Titular (nombre que aparece al pagar)</label>
                            <input class="form-control form-control-sm cfg-input" data-clave="PAGO_MANUAL_BILLETERA_TITULAR" placeholder="Nombre del titular">
                        </div>
                    </div>
                </div>

                <!-- Transferencia bancaria -->
                <div class="border rounded-3 p-3">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div>
                            <div class="fw-bold" style="font-size:14px;">Transferencia bancaria</div>
                            <small class="text-muted" style="font-size:11px;">El cliente transfiere a cualquiera de estos bancos y reporta a cuál. Llena titular + cuenta + CCI de los que uses.</small>
                        </div>
                        <div class="alert alert-warning py-2 mb-3" style="font-size:12px;">
                            <i class="bi bi-exclamation-triangle me-1"></i>
                            Los datos que coloques aquí (titular, n° de cuenta y CCI) son <strong>bajo tu responsabilidad</strong>: verifica que sean correctos antes de guardar, ya que el cliente pagará usando esa información tal como la veas.
                        </div>
                        <div class="form-check form-switch">
                            <input class="form-check-input cfg-switch" type="checkbox" data-clave="PAGO_MANUAL_TRANSFERENCIA_HABILITADO" id="transferenciaManualSwitch">
                        </div>
                    </div>
                    <div class="row g-3">
                        <?php foreach ($bancosManual as $b): ?>
                        <div class="col-12 col-lg-6">
                            <div class="bg-light rounded-3 p-3 h-100">
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <div class="fw-bold" style="font-size:13px;color:#23284E;"><?php echo $b['nombre']; ?></div>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input cfg-switch" type="checkbox" data-clave="PAGO_MANUAL_<?php echo $b['clave']; ?>_HABILITADO">
                                    </div>
                                </div>
                                <div class="mb-2">
                                    <label class="form-label fw-semibold text-muted mb-0" style="font-size:11px;">Titular</label>
                                    <input class="form-control form-control-sm cfg-input" data-clave="PAGO_MANUAL_<?php echo $b['clave']; ?>_TITULAR" placeholder="Nombre del titular">
                                </div>
                                <div class="mb-2">
                                    <label class="form-label fw-semibold text-muted mb-0" style="font-size:11px;">N° de cuenta</label>
                                    <input class="form-control form-control-sm cfg-input" data-clave="PAGO_MANUAL_<?php echo $b['clave']; ?>_CUENTA" placeholder="1234-567890-...">
                                </div>
                                <div>
                                    <label class="form-label fw-semibold text-muted mb-0" style="font-size:11px;">CCI (cuenta interbancaria)</label>
                                    <input class="form-control form-control-sm cfg-input" data-clave="PAGO_MANUAL_<?php echo $b['clave']; ?>_CCI" placeholder="00-1234-567890-...">
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="mt-4">
                    <label class="form-label fw-semibold text-muted" style="font-size:12px;">Instrucciones mostradas al cliente al pagar</label>
                    <textarea class="form-control form-control-sm cfg-input" data-clave="PAGO_MANUAL_INSTRUCCIONES" rows="2" placeholder="Ej: Transfiere el monto exacto y envía tu número de operación. El pedido queda en verificación hasta que lo confirmemos."></textarea>
                </div>
            </div>
            <div class="card-footer bg-transparent border-0 pb-4 pt-0 d-flex justify-content-end">
                <button type="button" class="btn btn-sm btn-guardar-seccion gp-btn-primary border-0 rounded-3 px-3 fw-semibold" style="font-size:12px;"><i class="bi bi-check2-circle me-1"></i> Guardar</button>
            </div>
        </div>

        <!-- Pasarelas de la tienda online -->
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body p-4">
                <h6 class="config-section-title mb-1">Pasarelas de la tienda online</h6>
                <p class="text-muted mb-4" style="font-size:11px;">
                    Habilita/deshabilita cada pasarela, elige el entorno (pruebas/producción) y pega las API Keys.
                    Las claves solo se pueden <strong>ingresar</strong>: nunca se muestran de vuelta (quedan protegidas en el servidor).
                    Deja un campo de clave en blanco para conservar la que ya está guardada.
                </p>

                <!-- TAYPI -->
                <div class="row g-4 mb-4">
                    <div class="col-12">
                        <div class="border rounded-3 p-3">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <div>
                                    <div class="fw-bold" style="font-size:14px;">TAYPI — QR Yape / Plin</div>
                                    <small class="text-muted" style="font-size:11px;">Código QR interoperable (Yape, Plin, BIM)</small>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input cfg-switch" type="checkbox" data-clave="TAYPI_HABILITADO" id="taypiSwitch">
                                </div>
                            </div>
                            <div class="row g-3">
                                <div class="col-12 col-md-3">
                                    <label class="form-label fw-semibold text-muted" style="font-size:12px;">Entorno</label>
                                    <select class="form-select form-select-sm cfg-input" data-clave="TAYPI_MODO">
                                        <option value="TEST">Sandbox (pruebas)</option>
                                        <option value="PRODUCCION">Producción</option>
                                    </select>
                                </div>
                                <div class="col-12 col-md-9">
                                    <label class="form-label fw-semibold text-muted" style="font-size:12px;">Public Key</label>
                                    <input type="password" class="form-control form-control-sm cfg-input cfg-secret" data-clave="TAYPI_PUBLIC_KEY" autocomplete="new-password" placeholder="taypi_pk_...">
                                    <small class="cfg-secret-hint text-muted" data-hint="TAYPI_PUBLIC_KEY" style="font-size:11px;"></small>
                                </div>
                                <div class="col-12 col-md-6">
                                    <label class="form-label fw-semibold text-muted" style="font-size:12px;">Secret Key (solo backend)</label>
                                    <input type="password" class="form-control form-control-sm cfg-input cfg-secret" data-clave="TAYPI_SECRET_KEY" autocomplete="new-password" placeholder="taypi_sk_...">
                                    <small class="cfg-secret-hint text-muted" data-hint="TAYPI_SECRET_KEY" style="font-size:11px;"></small>
                                </div>
                                <div class="col-12 col-md-6">
                                    <label class="form-label fw-semibold text-muted" style="font-size:12px;">Webhook Secret (solo backend)</label>
                                    <input type="password" class="form-control form-control-sm cfg-input cfg-secret" data-clave="TAYPI_WEBHOOK_SECRET" autocomplete="new-password" placeholder="whs_...">
                                    <small class="cfg-secret-hint text-muted" data-hint="TAYPI_WEBHOOK_SECRET" style="font-size:11px;"></small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Izipay -->
                <div class="row g-4">
                    <div class="col-12">
                        <div class="border rounded-3 p-3">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <div>
                                    <div class="fw-bold" style="font-size:14px;">Izipay — Tarjeta de débito/crédito</div>
                                    <small class="text-muted" style="font-size:11px;">Formulario embebido (Krypton). Claves de API REST del Back Office Vendedor</small>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input cfg-switch" type="checkbox" data-clave="IZIPAY_HABILITADO" id="izipaySwitch">
                                </div>
                            </div>
                            <div class="row g-3">
                                <div class="col-12 col-md-3">
                                    <label class="form-label fw-semibold text-muted" style="font-size:12px;">Entorno</label>
                                    <select class="form-select form-select-sm cfg-input" data-clave="IZIPAY_MODO">
                                        <option value="TEST">Sandbox (pruebas)</option>
                                        <option value="PRODUCCION">Producción</option>
                                    </select>
                                </div>
                                <div class="col-12 col-md-3">
                                    <label class="form-label fw-semibold text-muted" style="font-size:12px;">Shop ID</label>
                                    <input type="text" class="form-control form-control-sm cfg-input" data-clave="IZIPAY_SHOP_ID" autocomplete="off" placeholder="12345678">
                                </div>
                                <div class="col-12 col-md-6">
                                    <label class="form-label fw-semibold text-muted" style="font-size:12px;">Public Key</label>
                                    <input type="password" class="form-control form-control-sm cfg-input cfg-secret" data-clave="IZIPAY_PUBLIC_KEY" autocomplete="new-password" placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx">
                                    <small class="cfg-secret-hint text-muted" data-hint="IZIPAY_PUBLIC_KEY" style="font-size:11px;"></small>
                                </div>
                                <div class="col-12 col-md-6">
                                    <label class="form-label fw-semibold text-muted" style="font-size:12px;">Password (solo backend)</label>
                                    <input type="password" class="form-control form-control-sm cfg-input cfg-secret" data-clave="IZIPAY_PASSWORD" autocomplete="new-password" placeholder="••••••••">
                                    <small class="cfg-secret-hint text-muted" data-hint="IZIPAY_PASSWORD" style="font-size:11px;"></small>
                                </div>
                                <div class="col-12 col-md-6">
                                    <label class="form-label fw-semibold text-muted" style="font-size:12px;">HMAC SHA256 (solo backend)</label>
                                    <input type="password" class="form-control form-control-sm cfg-input cfg-secret" data-clave="IZIPAY_HMAC_SHA256" autocomplete="new-password" placeholder="••••••••">
                                    <small class="cfg-secret-hint text-muted" data-hint="IZIPAY_HMAC_SHA256" style="font-size:11px;"></small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-footer bg-transparent border-0 pb-4 pt-0 d-flex justify-content-end">
                <button type="button" class="btn btn-sm btn-guardar-seccion gp-btn-primary border-0 rounded-3 px-3 fw-semibold" style="font-size:12px;"><i class="bi bi-check2-circle me-1"></i> Guardar</button>
</div>
            </div>
            <!-- Código de confirmación de pedido (tienda online) -->
            <div class="card border-0 shadow-sm rounded-4 mb-3">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <h6 class="config-section-title mb-1">Código de confirmación de pedido</h6>
                            <p class="text-muted mb-0" style="font-size:11px;">
                                Al confirmarse una compra, el cliente recibe un código numérico de 4 dígitos
                                (en el correo de confirmación y en "Mis compras") para presentarlo al recojo.
                                En la entrega, el cajero pide ese código, con opción de saltarlo solo como última opción.
                            </p>
                        </div>
                        <div class="form-check form-switch ms-3">
                            <input class="form-check-input cfg-switch" type="checkbox" data-clave="CODIGO_CONFIRMACION_HABILITADO" id="codigoConfirmacionSwitch">
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-transparent border-0 pb-4 pt-0 d-flex justify-content-end">
                    <button type="button" class="btn btn-sm btn-guardar-seccion gp-btn-primary border-0 rounded-3 px-3 fw-semibold" style="font-size:12px;"><i class="bi bi-check2-circle me-1"></i> Guardar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Pestaña: Marca -->
    <div class="config-pane" id="pane-marca">
        <div class="card border-0 shadow-sm rounded-4 mb-3">
            <div class="card-body p-4">
                <h6 class="config-section-title mb-3">Identidad de la marca</h6>
                <p class="text-muted mb-3" style="font-size:12px;">
                    Estos valores se aplican en la tienda, el panel, los correos y los documentos (tickets/cotizaciones). El logo de cada zona se sube en "Logo y Marca" de la pestaña Datos de la Empresa.
                </p>
                <div class="row g-3">
                    <div class="col-12 col-md-6"><label class="form-label fw-semibold text-muted" style="font-size:12px;">Nombre de la marca</label><input class="form-control form-control-sm cfg-input" data-clave="MARCA_NOMBRE" placeholder="NISSI"></div>
                    <div class="col-12 col-md-6"><label class="form-label fw-semibold text-muted" style="font-size:12px;">Slogan</label><input class="form-control form-control-sm cfg-input" data-clave="MARCA_SLOGAN" placeholder="Uniforme escolar"></div>
                    <div class="col-12 col-md-6"><label class="form-label fw-semibold text-muted" style="font-size:12px;">Color primario</label><input type="color" class="form-control form-control-sm cfg-input cfg-color" data-clave="COLOR_PRIMARIO" style="height:38px;padding:4px;" title="Color principal (navy actual)"></div>
                    <div class="col-12 col-md-6"><label class="form-label fw-semibold text-muted" style="font-size:12px;">Color de acento</label><input type="color" class="form-control form-control-sm cfg-input cfg-color" data-clave="COLOR_ACENTO" style="height:38px;padding:4px;" title="Color de acento (rojo actual)"></div>
                    <div class="col-12"><label class="form-label fw-semibold text-muted" style="font-size:12px;">Frase del pie de correo</label><input class="form-control form-control-sm cfg-input" data-clave="MARCA_EMAIL_FOOTER" placeholder="Tienda de uniformes escolares · Todos los derechos reservados"></div>
                </div>
            </div>
            <div class="card-footer bg-transparent border-0 pb-4 pt-0 d-flex justify-content-end">
                <button type="button" class="btn btn-sm btn-guardar-seccion gp-btn-primary border-0 rounded-3 px-3 fw-semibold" style="font-size:12px;"><i class="bi bi-check2-circle me-1"></i> Guardar</button>
            </div>
        </div>

        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body p-4">
                <h6 class="config-section-title mb-3">Paletas sugeridas</h6>
                <p class="text-muted mb-3" style="font-size:12px;">
                    Selecciona un kit comercial para previsualizarlo y aplicarlo. También puedes escribir colores propios arriba.
                </p>
                <div class="row g-3" id="marcaPaletas"></div>
                <div class="mt-4">
                    <h6 class="config-section-title mb-2">Vista previa</h6>
                    <div id="marcaPreview" class="border rounded-4 p-4" style="background:#fff;">
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <div class="rounded-3 d-flex align-items-center justify-content-center" style="width:44px;height:44px;background:var(--navy, #23284E);color:#fff;font-weight:800;font-family:'Fraunces',serif;" id="pvLetra">N</div>
                            <div>
                                <div class="fw-bold" style="font-family:'Fraunces',serif;font-size:18px;color:var(--navy, #23284E);" id="pvNombre">NISSI</div>
                                <div class="text-muted" style="font-size:11px;letter-spacing:.25em;text-transform:uppercase;" id="pvSlogan">Uniforme escolar</div>
                            </div>
                        </div>
                        <div class="d-flex gap-2">
                            <button class="btn btn-sm border-0 text-white fw-semibold px-3" style="background:var(--navy, #23284E);" type="button">Guardar</button>
                            <button class="btn btn-sm border-0 text-white fw-semibold px-3" style="background:var(--accent, #BD1721);" type="button">Comprar</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Pestaña: Integraciones -->
    <div class="config-pane" id="pane-integraciones">
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body p-4">
                <h6 class="config-section-title mb-3">Servicios externos</h6>
                <p class="text-muted mb-3" style="font-size:12px;">
                    Claves de servicios usados por la tienda (correo, consulta DNI/RUC, WhatsApp). Se almacenan cifradas en la base de datos; deja en blanco para conservar la actual.
                </p>
                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <label class="form-label fw-semibold text-muted" style="font-size:12px;">Brevo API Key</label>
                        <input type="password" class="form-control form-control-sm cfg-input cfg-secret" data-clave="BREVO_API_KEY" autocomplete="new-password" placeholder="xkeysib-...">
                        <small class="cfg-secret-hint text-muted" data-hint="BREVO_API_KEY" style="font-size:11px;"></small>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label fw-semibold text-muted" style="font-size:12px;">Brevo remitente (email)</label>
                        <input type="email" class="form-control form-control-sm cfg-input" data-clave="BREVO_SENDER_EMAIL" placeholder="nissi_store@likadev.com">
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label fw-semibold text-muted" style="font-size:12px;">Brevo remitente (nombre)</label>
                        <input class="form-control form-control-sm cfg-input" data-clave="BREVO_SENDER_NAME" placeholder="Nissi">
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label fw-semibold text-muted" style="font-size:12px;">APIPerú Token (DNI/RUC)</label>
                        <input type="password" class="form-control form-control-sm cfg-input cfg-secret" data-clave="APIPERU_TOKEN" autocomplete="new-password" placeholder="token-...">
                        <small class="cfg-secret-hint text-muted" data-hint="APIPERU_TOKEN" style="font-size:11px;"></small>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label fw-semibold text-muted" style="font-size:12px;">WhatsApp de atención</label>
                        <input class="form-control form-control-sm cfg-input" data-clave="WHATSAPP_ATENCION" placeholder="+51 999 999 999">
                    </div>
                </div>
            </div>
            <div class="card-footer bg-transparent border-0 pb-4 pt-0 d-flex justify-content-end">
                <button type="button" class="btn btn-sm btn-guardar-seccion gp-btn-primary border-0 rounded-3 px-3 fw-semibold" style="font-size:12px;"><i class="bi bi-check2-circle me-1"></i> Guardar</button>
</div>
    </div>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    let datosConfig = {};

    // ── Pestañas ──
    document.querySelectorAll('.config-tab-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.config-tab-btn').forEach(b => b.classList.remove('active'));
            document.querySelectorAll('.config-pane').forEach(p => p.classList.remove('active'));
            btn.classList.add('active');
            document.getElementById('pane-' + btn.dataset.tab).classList.add('active');
        });
    });

    // ── Toggle mostrar/ocultar password ──
    document.querySelectorAll('.password-toggle-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const input = btn.closest('.input-group').querySelector('input');
            const esPassword = input.type === 'password';
            input.type = esPassword ? 'text' : 'password';
            btn.querySelector('i').className = esPassword ? 'bi bi-eye-slash' : 'bi bi-eye';
        });
    });

    // ── Vista previa local de archivos de imagen ──
    document.querySelectorAll('[data-archivo]').forEach(input => {
        input.addEventListener('change', () => {
            const clave = input.dataset.archivo;
            const file = input.files[0];
            if (!file) return;
            const preview = document.querySelector('[data-preview="' + clave + '"]');
            if (preview && file.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.onload = e => {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                };
                reader.readAsDataURL(file);
            }
        });
    });

    // ── Decodificación automática de QR de billetera (Yape/Plin) ──
    function pintarPreviewQR(clave, contenido) {
        const ta = document.querySelector('.cfg-input[data-clave="' + clave + '"]');
        const preview = ta && document.getElementById(ta.dataset.preview);
        if (preview && contenido && typeof QRCode === 'function') {
            preview.innerHTML = '';
            new QRCode(preview, { text: contenido, width: 96, height: 96, correctLevel: QRCode.CorrectLevel.M });
            preview.style.display = 'block';
        }
    }

    function decodificarQR(input) {
        const file = input.files[0];
        if (!file) return;
        const textarea = document.querySelector('.cfg-input[data-clave="' + input.dataset.target + '"]');
        if (!textarea) return;
        const img = new Image();
        const url = URL.createObjectURL(file);
        img.onload = () => {
            const canvas = document.createElement('canvas');
            const escala = Math.min(1, 1024 / img.width);
            canvas.width = Math.max(1, Math.round(img.width * escala));
            canvas.height = Math.max(1, Math.round(img.height * escala));
            const ctx = canvas.getContext('2d');
            ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
            let resultado = null;
            try {
                const datos = ctx.getImageData(0, 0, canvas.width, canvas.height);
                if (typeof jsQR === 'function') resultado = jsQR(datos.data, datos.width, datos.height, { inversionAttempts: 'dontInvert' });
            } catch (e) { /* imagen no legible */ }
            URL.revokeObjectURL(url);
            if (resultado && resultado.data) {
                textarea.value = resultado.data;
                input.classList.remove('is-invalid');
                input.classList.add('is-valid');
                pintarPreviewQR(textarea.dataset.clave, resultado.data);
                if (typeof Swal !== 'undefined') {
                    Swal.fire({ icon: 'success', title: 'QR decodificado', text: 'El QR se redibujará en el checkout. Presiona Guardar para aplicar.', toast: true, position: 'top-end', showConfirmButton: false, timer: 2200, confirmButtonColor: '#23284E' });
                }
            } else {
                input.classList.remove('is-valid');
                input.classList.add('is-invalid');
                if (typeof Swal !== 'undefined') Swal.fire({ icon: 'error', title: 'No se pudo leer el QR', text: 'Sube una imagen nítida del código QR (solo el QR, sin recortar).', confirmButtonColor: '#23284E' });
            }
        };
        img.onerror = () => {
            URL.revokeObjectURL(url);
            if (typeof Swal !== 'undefined') Swal.fire({ icon: 'error', title: 'Imagen inválida', confirmButtonColor: '#23284E' });
        };
        img.src = url;
    }
    document.querySelectorAll('.qr-auto-decodificar').forEach(input => {
        input.addEventListener('change', () => decodificarQR(input));
    });

    // ── Cargar datos actuales ──
    fetch('./controllers/C_Configuracion.php?action=obtener')
        .then(r => r.json())
        .then(json => {
            if (!json.success) throw new Error(json.mensaje);
            datosConfig = json.data;
            aplicarDatos(json.data);
        })
        .catch(() => {
            Swal.fire({ icon: 'error', title: 'No se pudo cargar la configuración', confirmButtonColor: '#23284E' });
        });

    function aplicarDatos(data) {
        document.querySelectorAll('.cfg-input').forEach(input => {
            const clave = input.dataset.clave;
            if (clave && data[clave]) input.value = data[clave].valor || '';
        });
        document.querySelectorAll('.cfg-sino').forEach(radio => {
            const clave = radio.dataset.clave;
            if (clave && data[clave] && radio.value === (data[clave].valor || '').toUpperCase()) radio.checked = true;
        });
        document.querySelectorAll('.cfg-switch').forEach(sw => {
            const clave = sw.dataset.clave;
            if (clave && data[clave]) sw.checked = (data[clave].valor || '').toUpperCase() === 'SI';
        });
        document.querySelectorAll('.cfg-secret-hint').forEach(small => {
            const clave = small.dataset.hint;
            if (clave && data[clave] && data[clave].tiene_valor) {
                small.innerHTML = '<i class="bi bi-check-circle-fill text-success"></i> Clave configurada (dejar en blanco para conservar)';
            } else {
                small.textContent = '';
            }
        });
        document.querySelectorAll('.cfg-file-current').forEach(small => {
            const clave = small.dataset.current;
            if (clave && data[clave] && data[clave].valor) {
                small.textContent = 'Actual: ' + data[clave].valor.split('/').pop() + (data[clave].desdeBD ? '' : ' (desde .env)');
            }
        });
        document.querySelectorAll('[data-preview]').forEach(img => {
            const clave = img.dataset.preview;
            if (clave && data[clave] && data[clave].valor) {
                img.src = data[clave].valor;
                img.style.display = 'block';
            }
        });
        renderPaletas();
        pintarPreview();
        pintarPreviewQR('PAGO_MANUAL_QR_YAPE_CONTENIDO', data['PAGO_MANUAL_QR_YAPE_CONTENIDO']?.valor || '');
        pintarPreviewQR('PAGO_MANUAL_QR_PLIN_CONTENIDO', data['PAGO_MANUAL_QR_PLIN_CONTENIDO']?.valor || '');
    }

    // ── Kit de paletas sugeridas + vista previa ──
    const PALETAS = [
        { nombre: 'Clásico NISSI', primario: '#23284E', acento: '#BD1721' },
        { nombre: 'Bosque',        primario: '#1F4E3D', acento: '#D99A2B' },
        { nombre: 'Acero Marino',  primario: '#1B2B5B', acento: '#0E7C86' },
        { nombre: 'Vino Elegante', primario: '#6E1E34', acento: '#C89B3C' },
        { nombre: 'Petróleo',      primario: '#0F3D3E', acento: '#C97B4A' },
        { nombre: 'Grafito',       primario: '#2B2D42', acento: '#E4572E' },
    ];

    function pintarPreview() {
        const primario = (document.querySelector('.cfg-color[data-clave="COLOR_PRIMARIO"]') || {}).value || '#23284E';
        const acento   = (document.querySelector('.cfg-color[data-clave="COLOR_ACENTO"]') || {}).value || '#BD1721';
        const nombre   = (document.querySelector('.cfg-input[data-clave="MARCA_NOMBRE"]') || {}).value || 'NISSI';
        const slogan   = (document.querySelector('.cfg-input[data-clave="MARCA_SLOGAN"]') || {}).value || 'Uniforme escolar';
        const pv = document.getElementById('marcaPreview');
        if (!pv) return;
        pv.style.setProperty('--navy', primario);
        pv.style.setProperty('--accent', acento);
        document.getElementById('pvLetra').textContent = (nombre || 'N').charAt(0).toUpperCase();
        document.getElementById('pvLetra').style.background = primario;
        document.getElementById('pvNombre').textContent = nombre;
        document.getElementById('pvNombre').style.color = primario;
        document.getElementById('pvSlogan').textContent = slogan;
    }

    function renderPaletas() {
        const cont = document.getElementById('marcaPaletas');
        if (!cont) return;
        cont.innerHTML = PALETAS.map(p => `
            <div class="col-6 col-md-4 col-lg-2">
                <button type="button" class="paleta-card w-100 border rounded-3 p-2 text-start" data-primario="${p.primario}" data-acento="${p.acento}" data-nombre="${p.nombre}" style="background:#fff;cursor:pointer;">
                    <div class="d-flex mb-2" style="border-radius:8px;overflow:hidden;height:22px;">
                        <div style="flex:1;background:${p.primario};"></div>
                        <div style="flex:1;background:${p.acento};"></div>
                    </div>
                    <div class="fw-semibold" style="font-size:11px;color:#1d2136;">${p.nombre}</div>
                </button>
            </div>`).join('');
        cont.querySelectorAll('.paleta-card').forEach(btn => {
            btn.addEventListener('click', () => {
                const c = document.querySelector('.cfg-color[data-clave="COLOR_PRIMARIO"]');
                const a = document.querySelector('.cfg-color[data-clave="COLOR_ACENTO"]');
                if (c) c.value = btn.dataset.primario;
                if (a) a.value = btn.dataset.acento;
                pintarPreview();
            });
        });
    }

    document.querySelectorAll('.cfg-color').forEach(inp => inp.addEventListener('input', pintarPreview));
    document.querySelector('.cfg-input[data-clave="MARCA_NOMBRE"]')?.addEventListener('input', pintarPreview);
    document.querySelector('.cfg-input[data-clave="MARCA_SLOGAN"]')?.addEventListener('input', pintarPreview);

    // ── Guardado: botón por sección (cada card) y botón por clave (password) ──

    const CONFIG_URL = './controllers/C_Configuracion.php';

    // Recoge los campos dentro de una card y arma el FormData.
    function formDataDeCard(card) {
        const formData = new FormData();
        card.querySelectorAll('.cfg-input').forEach(input => {
            // Los secretos en blanco NO se envían: el backend conserva el valor
            // actual (obtenerTodas nunca devuelve la clave real).
            if (input.classList.contains('cfg-secret') && !input.value.trim()) return;
            formData.append('campos[' + input.dataset.clave + ']', input.value);
        });
        card.querySelectorAll('.cfg-sino').forEach(radio => {
            if (radio.checked) formData.append('campos[' + radio.dataset.clave + ']', radio.value);
        });
        card.querySelectorAll('.cfg-switch').forEach(sw => {
            formData.append('campos[' + sw.dataset.clave + ']', sw.checked ? 'SI' : 'NO');
        });
        card.querySelectorAll('[data-archivo]').forEach(input => {
            if (input.files.length > 0) formData.append(input.dataset.archivo, input.files[0]);
        });
        return formData;
    }

    // Refresca SOLO los hints/vista previa dentro de una card, sin pisar inputs
    // que el admin aún esté editando en otras secciones.
    function refrescarCard(card, data) {
        card.querySelectorAll('.cfg-secret-hint').forEach(small => {
            const clave = small.dataset.hint;
            if (clave && data[clave] && data[clave].tiene_valor) {
                small.innerHTML = '<i class="bi bi-check-circle-fill text-success"></i> Clave configurada (dejar en blanco para conservar)';
            } else {
                small.textContent = '';
            }
        });
        card.querySelectorAll('.cfg-file-current').forEach(small => {
            const clave = small.dataset.current;
            if (clave && data[clave] && data[clave].valor) {
                small.textContent = 'Actual: ' + data[clave].valor.split('/').pop() + (data[clave].desdeBD ? '' : ' (desde .env)');
            }
        });
        card.querySelectorAll('[data-preview]').forEach(img => {
            const clave = img.dataset.preview;
            if (clave && data[clave] && data[clave].valor) {
                img.src = data[clave].valor;
                img.style.display = 'block';
            }
        });
    }

    // Botón de sección: guarda únicamente los campos de su card.
    document.querySelectorAll('.btn-guardar-seccion').forEach(btn => {
        btn.addEventListener('click', () => {
            const card = btn.closest('.card');
            const formData = formDataDeCard(card);
            const etiqueta = card.querySelector('.config-section-title')?.textContent.trim() || 'Sección';

            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Guardando...';

            fetch(CONFIG_URL + '?action=guardar', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(json => {
                    if (json.success) {
                        Swal.fire({ icon: 'success', title: 'Guardado', text: etiqueta + ' guardada correctamente.', toast: true, position: 'top-end', showConfirmButton: false, timer: 1800, confirmButtonColor: '#23284E' });
                        return fetch(CONFIG_URL + '?action=obtener').then(r => r.json());
                    }
                    throw new Error(json.mensaje);
                })
                .then(json => { if (json.success) refrescarCard(card, json.data); })
                .catch(e => {
                    Swal.fire({ icon: 'error', title: 'No se pudo guardar', text: e.message, confirmButtonColor: '#23284E' });
                })
                .finally(() => {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="bi bi-check2-circle me-1"></i> Guardar';
                });
        });
    });

    // Botón por clave: guarda solo ese campo password/secret sin tocar el resto.
    function agregarBotonesClave() {
        document.querySelectorAll('.cfg-input[type="password"]').forEach(input => {
            if (input.closest('.guardar-clave-wrap')) return;

            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'btn btn-outline-secondary btn-sm guardar-clave-btn';
            btn.title = 'Guardar esta clave';
            btn.innerHTML = '<i class="bi bi-check2"></i>';

            const group = input.closest('.input-group');
            if (group) {
                const wrapper = group;
                const existing = wrapper.querySelector('.guardar-clave-btn');
                if (existing) return;
                wrapper.appendChild(btn);
            } else {
                const wrapper = document.createElement('div');
                wrapper.className = 'input-group input-group-sm guardar-clave-wrap';
                input.parentNode.insertBefore(wrapper, input);
                wrapper.appendChild(input);
                wrapper.appendChild(btn);
            }

            btn.addEventListener('click', () => {
                const clave = input.dataset.clave;
                const formData = new FormData();
                if (input.value.trim()) formData.append('campos[' + clave + ']', input.value);

                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

                fetch(CONFIG_URL + '?action=guardar', { method: 'POST', body: formData })
                    .then(r => r.json())
                    .then(json => {
                        if (json.success) {
                            Swal.fire({ icon: 'success', title: 'Clave guardada', toast: true, position: 'top-end', showConfirmButton: false, timer: 1800, confirmButtonColor: '#23284E' });
                            input.value = '';
                            return fetch(CONFIG_URL + '?action=obtener').then(r => r.json());
                        }
                        throw new Error(json.mensaje);
                    })
                    .then(json => {
                        if (json.success) {
                            const card = input.closest('.card');
                            if (card) refrescarCard(card, json.data);
                        }
                    })
                    .catch(e => {
                        Swal.fire({ icon: 'error', title: 'No se pudo guardar', text: e.message, confirmButtonColor: '#23284E' });
                    })
                    .finally(() => {
                        btn.disabled = false;
                        btn.innerHTML = '<i class="bi bi-check2"></i>';
                    });
            });
        });
    }
    agregarBotonesClave();
});
</script>
