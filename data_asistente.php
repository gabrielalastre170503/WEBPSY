<?php
/**
 * Base de conocimiento del asistente local (paciente).
 *
 * construir_kb(mysqli $conex): array
 *   Devuelve un arreglo de entradas: ['q' => titulo, 'a' => respuesta, 'tags' => palabras clave, 'link' => ['href','label']|null]
 *
 * El asistente del buscador de FAQ usa estas entradas con coincidencia por
 * palabras clave + sinónimos. Para ampliar la cobertura solo agrega entradas
 * a $estaticas o ajusta los textos generados por tipo de estudio.
 */

if (!function_exists('construir_kb')) {

    /** Preparación corta por categoría de estudio. */
    function kb_prep_corta(): array
    {
        return [
            'Abdominal'           => 'Requiere ayuno de 6 a 8 horas (no comas ni tomes bebidas con gas; puedes beber agua).',
            'Renal'              => 'Acude con la vejiga llena: bebe 4 a 6 vasos de agua una hora antes y no orines hasta el estudio.',
            'Cervical'           => 'No requiere preparación. Evita cremas en el cuello.',
            'Mamaria'            => 'No requiere preparación. El día del estudio evita cremas, talcos o desodorante en la zona.',
            'Musculoesqueletica' => 'No requiere preparación. Usa ropa cómoda que permita exponer la zona.',
            'Obstetrica'         => 'Primer trimestre: vejiga llena. Segundo y tercer trimestre: no requiere preparación especial.',
            'Partes Blandas'     => 'No requiere preparación. Informa al ecografista la zona exacta de la molestia.',
            'Pelvica'            => 'Acude con la vejiga llena: bebe 4 a 6 vasos de agua una hora antes y no orines hasta el estudio.',
            'Prostatica'         => 'Acude con la vejiga llena: bebe 4 a 6 vasos de agua una hora antes del estudio.',
            'Pulmonar'           => 'No requiere preparación. Acude con ropa cómoda.',
            'Testicular'         => 'No requiere preparación especial. Sigue las indicaciones de tu médico tratante.',
        ];
    }

    /** Descripción ("qué evalúa") por categoría. */
    function kb_desc_categoria(): array
    {
        return [
            'Abdominal'           => 'evalúa órganos del abdomen como hígado, vesícula y vías biliares, páncreas, bazo y riñones',
            'Renal'              => 'evalúa los riñones y las vías urinarias',
            'Cervical'           => 'evalúa la glándula tiroides y otras estructuras del cuello',
            'Mamaria'            => 'evalúa las mamas y suele complementar a la mamografía',
            'Musculoesqueletica' => 'evalúa músculos, tendones, ligamentos y articulaciones',
            'Obstetrica'         => 'controla el embarazo y el desarrollo del bebé',
            'Partes Blandas'     => 'evalúa bultos, quistes o lesiones por debajo de la piel',
            'Pelvica'            => 'evalúa la pelvis: útero y ovarios en la mujer, y la vejiga',
            'Prostatica'         => 'evalúa la próstata y la vejiga',
            'Pulmonar'           => 'evalúa los pulmones y la pleura',
            'Testicular'         => 'evalúa los testículos y el escroto',
        ];
    }

    function construir_kb(mysqli $conex): array
    {
        $kb = [];
        $add = function (string $q, string $a, string $tags = '', ?array $link = null) use (&$kb) {
            $kb[] = ['q' => $q, 'a' => $a, 'tags' => $tags, 'link' => $link];
        };

        $L_solicitar = ['href' => 'solicitar_cita_paciente.php', 'label' => 'Solicitar cita'];
        $L_citas     = ['href' => 'mis_citas_paciente.php', 'label' => 'Mis citas'];
        $L_informes  = ['href' => 'mis_informes_paciente.php', 'label' => 'Mis informes'];
        $L_prep      = ['href' => 'preparacion_estudios_paciente.php', 'label' => 'Preparación de estudios'];
        $L_eco       = ['href' => 'ecografistas_paciente.php', 'label' => 'Ver ecografistas'];
        $L_ayuda     = ['href' => 'paciente_ayuda.php', 'label' => 'Centro de Ayuda'];
        $L_perfil    = ['href' => 'perfil.php', 'label' => 'Mi perfil'];

        /* ── 1) FAQs activas desde la BD ── */
        if ($fr = $conex->query('SELECT pregunta, respuesta FROM faqs WHERE activa = 1 ORDER BY orden ASC, id ASC')) {
            while ($f = $fr->fetch_assoc()) {
                $add($f['pregunta'], $f['respuesta'], '');
            }
            $fr->free();
        }

        /* ── 2) Entradas generadas por cada tipo de estudio ── */
        $prep = kb_prep_corta();
        $desc = kb_desc_categoria();
        if ($rt = $conex->query("SELECT nombre, categoria, descripcion FROM tipos_ecografias
            WHERE activo = 1 AND (categoria IS NULL OR categoria NOT IN ('Musculoesqueletica_Sub','Obstetrica_Sub','Partes_Blandas_Sub'))
            ORDER BY categoria, posicion, nombre")) {
            while ($t = $rt->fetch_assoc()) {
                $n   = $t['nombre'];
                $cat = $t['categoria'];
                $dsc = trim((string)($t['descripcion'] ?? ''));
                if ($dsc === '') {
                    $dsc = 'Es un estudio por ultrasonido que ' . ($desc[$cat] ?? 'evalúa la zona indicada por tu médico') . '.';
                }
                $prp = $prep[$cat] ?? 'Consulta la preparación en la sección Preparación de Estudios.';
                $verbo = $desc[$cat] ?? 'evalúa la zona que indica tu médico';
                $tagbase = $n . ' ' . $cat . ' estudio ecografia eco';
                $ayuno   = ($cat === 'Abdominal');

                $add("¿Qué es la $n?", "$n. $dsc", "$tagbase que es significa funcion en que consiste", $L_solicitar);
                $add("¿Para qué sirve la $n?", "La $n $verbo. Tu médico la indica según tu caso.", "$tagbase para que sirve utilidad proposito objetivo cuando se hace indicacion detecta", $L_solicitar);
                $add("¿Cómo me preparo para la $n?", "Para la $n: $prp", "$tagbase preparacion preparar prepararme como me preparo ayuno vejiga indicaciones", $L_prep);
                $add("¿La $n duele?", "La $n no es dolorosa. El ecografista aplica un gel y desliza un transductor sobre la piel; a lo sumo podrías sentir una leve presión.", "$tagbase duele dolor molesta molestia incomoda", null);
                $add("¿La $n es segura?", "Sí. La $n usa ultrasonido, no radiación, por lo que es segura e indolora; puede repetirse sin problema cuando tu médico lo indique.", "$tagbase segura seguridad riesgo radiacion peligrosa daña efectos", null);
                $add("¿Cuánto dura la $n?", "La $n suele durar entre 15 y 30 minutos, según el caso.", "$tagbase dura duracion tiempo cuanto tarda minutos", null);
                $add("¿Necesito orden médica para la $n?", "Es recomendable traer la indicación de tu médico para la $n. Si no la tienes, consúltalo con recepción.", "$tagbase orden medica indicacion referencia receta requiere necesito papel", $L_ayuda);
                $add("¿Cuánto cuesta la $n?", "El costo de la $n lo confirma recepción al momento de agendar, ya que varía según el estudio.", "$tagbase precio costo cuesta vale tarifa cuanto sale valor", $L_ayuda);
                $add("¿Cuándo tendré el resultado de la $n?", "El informe de la $n estará disponible en “Mis Informes” cuando el ecografista lo finalice o firme.", "$tagbase resultado informe cuando listo entrega demora reporte", $L_informes);
                if ($ayuno) {
                    $add("¿Puedo comer antes de la $n?", "No. La $n requiere ayuno de 6 a 8 horas. Puedes beber agua y tomar tu medicación habitual.", "$tagbase comer comida desayunar ayuno antes puedo", $L_prep);
                } else {
                    $add("¿Puedo comer antes de la $n?", "Sí. La $n no requiere ayuno; puedes comer y tomar tu medicación con normalidad.", "$tagbase comer comida desayunar ayuno antes puedo", $L_prep);
                }
            }
            $rt->free();
        }

        /* ── 3) Conocimiento general (curado) ── */

        /* Citas y agenda */
        $add('¿Cómo solicito una cita?', 'Entra a “Solicitar nueva cita”, elige el tipo de estudio, el ecografista, la fecha y la hora disponibles, y envía la solicitud. Te avisaremos cuando el ecografista la confirme.', 'agendar pedir reservar nueva cita turno solicitar programar como hago sacar', $L_solicitar);
        $add('¿Puedo elegir a mi ecografista?', 'Sí. Al solicitar la cita seleccionas la especialidad y luego el ecografista disponible que prefieras.', 'elegir escoger seleccionar ecografista doctor preferido especifico', $L_solicitar);
        $add('¿Cómo reprogramo o cancelo mi cita?', 'Desde “Mis Citas” abre los detalles de la cita. Si el ecografista propone una nueva fecha, verás opciones para aceptarla o rechazarla. Para otros cambios, contacta a recepción.', 'cambiar mover reprogramar reagendar cancelar anular cita fecha', $L_citas);
        $add('¿Cuánto tarda en confirmarse mi cita?', 'Tu solicitud queda como “Pendiente” hasta que el ecografista la revisa y la confirma. Te llegará una notificación cuando cambie de estado.', 'tarda confirmacion confirmar pendiente cuanto demora espera aprobar', $L_citas);
        $add('¿Qué significan los estados de mi cita?', 'Pendiente: en revisión. Confirmada: aprobada con fecha y hora. Pospuesta/Reprogramada: el profesional propuso otra fecha (puedes aceptar o rechazar). Completada: ya se realizó. Cancelada/Rechazada: no se llevará a cabo.', 'estado estados significa pendiente confirmada pospuesta reprogramada completada cancelada rechazada badge', $L_citas);
        $add('El profesional propuso otra fecha, ¿qué hago?', 'Abre la cita en “Mis Citas”; verás la nueva fecha sugerida con botones para Aceptar o Rechazar la propuesta.', 'propuesta nueva fecha aceptar rechazar pospuesta reprogramada sugerida', $L_citas);
        $add('¿Puedo tener varias citas a la vez?', 'Sí, puedes solicitar varios estudios. Cada uno aparecerá por separado en “Mis Citas” con su propio estado.', 'varias multiples varios estudios citas mismo dia a la vez', $L_citas);
        $add('¿Qué pasa si llego tarde?', 'Llega 10–15 minutos antes. Si llegas tarde puede que tu estudio se reprograme según la disponibilidad. Avisa a recepción cuanto antes.', 'tarde retraso llegar atrasado demora impuntual', $L_ayuda);
        $add('¿Qué pasa si no asisto a mi cita?', 'Si no puedes asistir, cancela o reprograma con anticipación desde “Mis Citas” o contactando a recepción, para liberar el cupo.', 'no asisto falto inasistencia ausencia no voy perder cita', $L_citas);

        /* Pagos y seguros */
        $add('¿Cuánto cuesta el estudio?', 'El costo depende del tipo de ecografía. Consulta el precio actualizado con recepción o en el Centro de Ayuda al agendar.', 'precio costo tarifa valor cuesta cuanto vale pagar cobran cuanto sale', $L_ayuda);
        $add('¿Qué formas de pago aceptan?', 'Las formas de pago disponibles las indica recepción al momento de tu cita. Consúltalas en el Centro de Ayuda.', 'pago pagar efectivo tarjeta transferencia metodos formas como pago', $L_ayuda);
        $add('¿Me dan factura?', 'Sí, recepción puede emitir el comprobante o factura de tu estudio. Solicítalo el día de tu cita.', 'factura comprobante recibo boleta facturar', $L_ayuda);
        $add('¿Trabajan con seguros o HCM?', 'La cobertura depende de tu aseguradora y del convenio vigente. Confirma con recepción antes de tu cita qué documentos necesitas.', 'seguro seguros aseguradora poliza cobertura convenio hcm reembolso carta aval', $L_ayuda);

        /* Llegada y logística */
        $add('¿A qué hora debo llegar?', 'Te recomendamos llegar 10 a 15 minutos antes de tu cita para el registro.', 'hora llegar antes temprano puntualidad cuando llego anticipacion', null);
        $add('¿Qué debo llevar a mi estudio?', 'Lleva tu documento de identidad, la orden médica si la tienes, y estudios o informes previos relacionados.', 'llevar traer documento orden cedula requisitos que necesito papeles', null);
        $add('¿Necesito una orden médica?', 'Es recomendable traer la indicación de tu médico tratante, sobre todo para estudios específicos. Si no la tienes, consúltalo con recepción.', 'orden medica indicacion referencia receta necesito requiere', $L_ayuda);
        $add('¿Puedo ir acompañado?', 'Sí, puedes asistir con un acompañante. En estudios obstétricos suele ser bienvenido; por espacio, a veces se permite el ingreso de una sola persona.', 'acompañante acompañado pareja familiar entrar sala persona', null);
        $add('¿Puedo llevar a mis hijos?', 'Puedes asistir con tus hijos, aunque por comodidad y espacio te sugerimos ir con un acompañante que pueda cuidarlos durante el estudio.', 'hijos niños bebe llevar cuidar acompañante', null);
        $add('¿Qué ropa debo usar?', 'Usa ropa cómoda y fácil de retirar en la zona a evaluar. Para estudios de mama u obstétricos, la ropa de dos piezas es práctica.', 'ropa vestir comoda que me pongo dos piezas', $L_prep);
        $add('¿Dónde están ubicados? / ¿Cuál es la dirección?', 'La dirección, horarios y datos de contacto de la clínica están disponibles en el Centro de Ayuda.', 'ubicacion direccion donde quedan estan mapa como llegar sede sucursal', $L_ayuda);
        $add('¿Cuál es el horario de atención?', 'El horario de atención lo encuentras en el Centro de Ayuda. La disponibilidad de cada ecografista la ves al agendar tu cita.', 'horario atencion abren cierran hora dias laborables abierto', $L_ayuda);
        $add('¿Cómo los contacto? / ¿Tienen teléfono?', 'Encuentras los datos de contacto (teléfono y correo) en el Centro de Ayuda.', 'contacto telefono numero llamar correo email whatsapp comunicar', $L_ayuda);

        /* Preparación general */
        $add('¿Necesito ayuno para mi estudio?', 'Depende del estudio. La ecografía abdominal o abdominal total requiere ayuno de 6 a 8 horas. La mayoría de los demás no lo requieren. Revisa la sección de Preparación.', 'ayuno comer comida desayunar abdominal preparacion en ayunas necesito', $L_prep);
        $add('¿Por qué debo tomar agua y no orinar?', 'Para estudios pélvicos, renales, de próstata u obstétricos de primer trimestre se necesita la vejiga llena: mejora la visualización. Bebe 4–6 vasos de agua una hora antes y no orines hasta el estudio.', 'vejiga llena agua orinar tomar liquido pelvica renal prostata por que', $L_prep);
        $add('¿Puedo tomar mis medicamentos antes del estudio?', 'Sí, en general puedes tomar tu medicación habitual con un poco de agua, incluso en estudios con ayuno. Ante dudas, consulta a tu médico.', 'medicamento medicina pastilla tomar antes pildora tratamiento puedo', null);
        $add('¿Puedo fumar antes del estudio?', 'Para la ecografía abdominal evita fumar antes del estudio, ya que puede afectar la visualización. En otros estudios no es relevante.', 'fumar cigarro tabaco antes abdominal', null);
        $add('¿Las ecografías son seguras durante el embarazo?', 'Sí. La ecografía usa ultrasonido (no radiación) y es segura para ti y tu bebé; por eso es el estudio de elección para el control del embarazo.', 'embarazo embarazada segura seguro riesgo bebe radiacion daña gestacion', $L_prep);
        $add('¿La ecografía usa radiación?', 'No. La ecografía funciona con ondas de ultrasonido, no con radiación, por eso es muy segura y se repite sin problema.', 'radiacion rayos x segura ondas ultrasonido contraste daña', null);
        $add('¿El gel es frío? ¿Mancha la ropa?', 'El gel puede sentirse algo fresco al aplicarlo y se limpia fácilmente al terminar; no mancha la piel y sale de la ropa con agua.', 'gel frio mancha ropa pegajoso limpiar', null);
        $add('¿Puedo hacerme la ecografía si estoy menstruando?', 'En la mayoría de los casos sí. Para estudios pélvicos, si tienes dudas por la menstruación, consúltalo con recepción o tu médico.', 'menstruacion regla periodo pelvica puedo durante sangrado', $L_ayuda);

        /* Resultados e informes */
        $add('¿Dónde veo mis resultados o informes?', 'En la sección “Mis Informes” están los informes finalizados y firmados de tus estudios. Puedes abrirlos e imprimirlos.', 'resultado resultados informe reporte estudio ver consultar descargar imprimir donde', $L_informes);
        $add('¿Cuándo estarán listos mis resultados?', 'Tu informe aparece en “Mis Informes” cuando el ecografista lo finaliza o firma. Los tiempos varían según el estudio; recepción puede orientarte.', 'cuando listos demora tardan resultados informe tiempo entrega', $L_informes);
        $add('¿Cómo descargo o imprimo mi informe?', 'Abre el informe desde “Mis Informes” y usa la opción de imprimir de tu navegador, que también permite guardarlo como PDF.', 'descargar imprimir pdf guardar informe resultado bajar', $L_informes);
        $add('¿Qué diferencia hay entre informe finalizado y firmado?', 'Ambos están disponibles para ti. “Firmado” indica que el ecografista lo validó con su firma; “Finalizado” es el informe completado y listo para consulta.', 'finalizado firmado diferencia estado informe firma', $L_informes);
        $add('¿Puedo compartir mi informe con mi médico?', 'Sí. Puedes imprimir tu informe o guardarlo como PDF desde “Mis Informes” y entregarlo a tu médico tratante.', 'compartir enviar medico tratante informe entregar mostrar', $L_informes);
        $add('¿Qué significa mi resultado? ¿Es grave?', 'La interpretación clínica de tu informe corresponde al ecografista y a tu médico tratante. Si tienes dudas sobre el contenido, coméntalas con tu médico, quien conoce tu caso completo.', 'significa resultado grave interpretar entender hallazgo normal anormal preocupante diagnostico', $L_informes);

        /* Cuenta y sistema */
        $add('¿Cómo cambio mi contraseña?', 'Ve a “Mi Perfil” para actualizar tu contraseña y tus datos de acceso.', 'contraseña clave cambiar password actualizar seguridad acceso', $L_perfil);
        $add('Olvidé mi contraseña, ¿qué hago?', 'En la pantalla de inicio de sesión usa la opción de recuperación, o contacta a recepción para restablecer tu acceso.', 'olvide recuperar contraseña clave password no puedo entrar acceso restablecer', $L_ayuda);
        $add('¿Cómo actualizo mis datos personales?', 'Desde “Mi Perfil” puedes actualizar tus datos de contacto como correo y teléfono.', 'actualizar datos personales correo telefono direccion perfil cambiar editar', $L_perfil);
        $add('¿Cómo activo el modo oscuro?', 'Usa el botón de luna/sol en la barra superior para cambiar entre modo claro y oscuro. Tu preferencia se guarda automáticamente.', 'modo oscuro tema noche claro dark cambiar apariencia color', null);
        $add('¿Cómo cierro sesión?', 'Usa el botón de salir junto a tu nombre en la parte inferior del menú lateral.', 'cerrar sesion salir logout desconectar', null);
        $add('¿Mis datos están seguros? ¿Es confidencial?', 'Sí. Tu información y tus estudios son confidenciales y solo son accesibles para ti y el personal autorizado de la clínica.', 'seguro confidencial privacidad datos privado proteccion secreto informacion', null);

        /* Información de equipo y ayuda */
        $add('¿Quiénes son los ecografistas?', 'Puedes ver el equipo, sus especialidades y su perfil en la sección “Ecografistas Activos”.', 'medico doctor profesional ecografista equipo quien especialista perfil curriculum', $L_eco);
        $add('Necesito ayuda de una persona', 'Si tu duda no se resuelve aquí, visita el Centro de Ayuda o contacta a recepción y con gusto te atendemos.', 'ayuda contacto recepcion hablar persona soporte humano asesor agente', $L_ayuda);

        /* Embarazo / obstétrica (detalle) */
        $add('¿En qué semana es mi primera ecografía de embarazo?', 'La primera ecografía suele realizarse entre las semanas 6 y 9 para confirmar el embarazo. Tu médico te indicará el momento exacto según tu caso.', 'semana primera ecografia embarazo cuando obstetrica confirmar gestacion inicio', $L_solicitar);
        $add('¿En qué semana puedo saber el sexo del bebé?', 'Generalmente el sexo del bebé puede observarse desde la semana 16 a 20, en la ecografía del segundo trimestre, si la posición del bebé lo permite.', 'sexo bebe genero niño niña saber semana cuando ver descubrir', null);
        $add('¿Cuántas ecografías me harán durante el embarazo?', 'Depende de la indicación de tu obstetra; lo habitual es al menos una por trimestre. Tu médico define el seguimiento de tu embarazo.', 'cuantas ecografias embarazo numero control seguimiento trimestre veces', null);
        $add('¿Qué es una ecografía Doppler?', 'El Doppler es una técnica que evalúa el flujo de sangre en arterias y venas. Puede formar parte de distintos estudios según lo indique tu médico.', 'doppler flujo sangre vascular venas arterias color que es circulacion', null);
        $add('¿Hacen ecografías 3D o 4D?', 'Las modalidades 3D/4D muestran imágenes en volumen, usadas sobre todo en obstetricia. Consulta su disponibilidad con recepción.', '3d 4d tridimensional volumen bebe rostro disponible hacen tienen', $L_ayuda);
        $add('¿La ecografía transvaginal duele?', 'No suele ser dolorosa; puede generar una leve molestia. El ecografista te explica el procedimiento y respeta tu comodidad en todo momento.', 'transvaginal vaginal duele molesta interna pelvica incomoda introducir', null);
        $add('¿Puedo ir maquillada a la ecografía mamaria?', 'Para la ecografía mamaria evita cremas, talcos o desodorante en la zona del estudio; el maquillaje facial no afecta el resultado.', 'maquillaje maquillada crema desodorante talco mama mamaria zona', null);

        /* Estudios: dudas frecuentes */
        $add('¿La ecografía detecta cáncer o tumores?', 'La ecografía ayuda a detectar y caracterizar quistes, nódulos o lesiones, pero el diagnóstico final lo establece tu médico con el conjunto de tus estudios.', 'cancer tumor deteccion nodulo quiste lesion maligno detecta grave bulto', $L_informes);
        $add('¿Necesito rasurar o depilar la zona?', 'No es necesario rasurarte. Solo acude con la piel limpia y sin cremas en la zona a evaluar.', 'rasurar afeitar depilar vello zona piel necesito', null);
        $add('¿Cuál es la diferencia entre ecografía abdominal y pélvica?', 'La abdominal evalúa órganos del abdomen y requiere ayuno; la pélvica evalúa la pelvis y requiere vejiga llena. Tu médico indica cuál necesitas.', 'diferencia abdominal pelvica cual entre comparar distinto', $L_prep);
        $add('¿Hacen ecografías a niños o bebés?', 'Sí, realizamos estudios pediátricos. La preparación depende del tipo de estudio; recepción te orientará para que tu hijo asista cómodo.', 'niños bebes pediatrica infantil hijo hacen menores pediatrico', $L_ayuda);
        $add('¿Puedo hacerme varios estudios el mismo día?', 'En muchos casos sí, pero algunos tienen preparaciones opuestas (ayuno frente a vejiga llena). Coordina con recepción el orden adecuado.', 'varios estudios mismo dia juntos a la vez combinar multiples seguidos', $L_ayuda);
        $add('¿La ecografía tiene efectos secundarios?', 'No. La ecografía no tiene efectos secundarios conocidos; es un estudio seguro e indoloro que usa ultrasonido, no radiación.', 'efectos secundarios riesgos consecuencias daña segura indolora contraindicaciones', null);

        /* Preparación: detalles del ayuno y la hidratación */
        $add('¿Puedo tomar café o leche durante el ayuno?', 'Durante el ayuno evita café, leche y bebidas con gas, ya que pueden afectar el estudio abdominal. Sí puedes tomar agua.', 'cafe te leche ayuno tomar bebida puedo abdominal infusion', $L_prep);
        $add('¿Puedo mascar chicle en ayuno?', 'Es preferible evitar el chicle durante el ayuno, porque estimula la digestión y puede afectar la ecografía abdominal.', 'chicle goma mascar ayuno puedo masticar', null);
        $add('¿Puedo tomar agua antes del estudio?', 'Sí. El agua está permitida, incluso en estudios con ayuno. En estudios con vejiga llena, además debes beber agua antes para llenarla.', 'agua tomar beber antes ayuno permitido liquido hidratar', $L_prep);
        $add('Soy diabético, ¿debo hacer ayuno?', 'Si tienes diabetes y te indicaron ayuno, consulta con tu médico cómo manejar tu alimentación y medicación ese día. No suspendas tratamientos por tu cuenta.', 'diabetes diabetico ayuno azucar insulina medicacion debo glucemia', null);

        /* Resultados e imágenes */
        $add('¿Me entregan las imágenes o placas del estudio?', 'El informe incluye las imágenes representativas del estudio. Si necesitas las imágenes en otro formato, consúltalo con recepción.', 'imagenes placas fotos cd dvd entregan dan impresas fotografias', $L_informes);
        $add('¿Me explican el resultado el mismo día?', 'El ecografista elabora el informe del estudio; la interpretación clínica y la conducta a seguir las define tu médico tratante. Tu informe quedará en “Mis Informes”.', 'explican resultado mismo dia hablan dicen al momento interpretar diagnostico', $L_informes);

        /* Cuenta y registro */
        $add('¿Cómo creo una cuenta o me registro?', 'Desde la página de registro completas tus datos. Cuando tu cuenta sea aprobada podrás solicitar citas y consultar tus informes.', 'registro registrar crear cuenta nueva inscribir alta usuario como me registro', null);
        $add('¿Por qué mi cuenta aparece pendiente de aprobación?', 'Las cuentas nuevas pasan por una verificación del personal de la clínica. Cuando se apruebe, podrás agendar con normalidad.', 'pendiente aprobacion cuenta verificacion activar esperando porque revision', $L_ayuda);
        $add('No me llegan las notificaciones', 'Las notificaciones aparecen dentro del sistema (campana de la barra superior y en “Mis Citas”). Revisa esa sección; si falta algo, contacta a recepción.', 'notificaciones avisos no llegan recibo alertas campana mensajes', $L_citas);

        /* Agenda y disponibilidad */
        $add('¿Con cuánta anticipación debo agendar?', 'Te recomendamos agendar con anticipación para asegurar el cupo en la fecha y hora que prefieras. La disponibilidad la ves al solicitar la cita.', 'anticipacion cuanto antes agendar reservar dias previos cuanto tiempo planificar', $L_solicitar);
        $add('¿Atienden sin cita previa?', 'La atención es con cita para organizar la agenda y reducir esperas. Solicita la tuya desde “Solicitar nueva cita”.', 'sin cita previa walk in espontaneo llegar directo urgencia ahora mismo', $L_solicitar);
        $add('¿Puedo agendar una cita para otra persona?', 'Cada paciente gestiona sus citas desde su propia cuenta. Si necesitas ayuda para un familiar, contacta a recepción.', 'otra persona familiar tercero agendar para alguien hijo madre esposo', $L_ayuda);

        /* Meta: sobre el asistente */
        $add('¿Qué puedes hacer? ¿En qué me ayudas?', 'Puedo ayudarte con dudas sobre citas, estudios, preparación, resultados y el uso del sistema. Escríbeme tu pregunta y te oriento.', 'que puedes hacer ayudas sirves funciones para que eres util capaz', null);
        $add('¿Eres una persona o un robot?', 'Soy un asistente automático de la clínica. Para atención personalizada, visita el Centro de Ayuda o contacta a recepción.', 'eres robot persona humano bot real maquina quien eres inteligencia artificial', $L_ayuda);

        /* Durante y después del estudio */
        $add('¿Cómo es el procedimiento de la ecografía?', 'El ecografista aplica un gel sobre la piel y desliza un transductor por la zona a evaluar mientras observa las imágenes en pantalla. Es indoloro y no usa agujas ni radiación.', 'procedimiento como es se hace pasos durante transductor gel sonda en que consiste', null);
        $add('¿Tengo que desvestirme? ¿Me dan una bata?', 'Solo necesitas descubrir la zona a evaluar. En los estudios que lo requieren se te facilita una bata y privacidad para cambiarte.', 'desvestir quitar ropa bata cambiarme descubrir privacidad desnudar', null);
        $add('Tengo la vejiga muy llena, ¿puedo ir al baño?', 'Si la molestia es mucha, avisa al ecografista: a veces puede permitir vaciar parcialmente la vejiga. Intenta mantenerla llena hasta el inicio del estudio.', 'vejiga llena baño orinar aguantar no aguanto pis ganas', null);
        $add('¿Puedo retomar mis actividades después del estudio?', 'Sí. La ecografía no requiere reposo: puedes comer, conducir y seguir tu día con normalidad al terminar.', 'despues retomar actividades reposo normal conducir manejar trabajar terminar', null);
        $add('¿Puedo comer después del estudio?', 'Sí. Si hiciste ayuno, puedes comer apenas termine el estudio.', 'despues comer ayuno terminar puedo luego', null);

        /* Detalle por tipo de estudio */
        $add('¿La ecografía renal detecta cálculos o piedras?', 'La ecografía renal evalúa los riñones y puede detectar cálculos, quistes u otras alteraciones. Tu médico interpreta los hallazgos.', 'renal calculos piedras litiasis riñon detecta arena obstruccion', $L_solicitar);
        $add('¿La ecografía abdominal detecta cálculos en la vesícula o hígado graso?', 'Sí. La ecografía abdominal evalúa hígado, vesícula y vías biliares, y puede mostrar cálculos o hígado graso, entre otros hallazgos.', 'abdominal vesicula calculos higado graso piedras detecta colelitiasis esteatosis', $L_solicitar);
        $add('¿La ecografía mamaria reemplaza la mamografía?', 'No la reemplaza; la complementa. Tu médico indica cuál corresponde según tu edad y tu caso.', 'mama mamaria mamografia reemplaza diferencia complementa edad sustituye', null);
        $add('¿La ecografía de próstata es transrectal?', 'Existe el abordaje abdominal (con vejiga llena) y, según la indicación médica, el transrectal. Recepción te informa cuál se realiza en tu caso.', 'prostata transrectal abdominal como se hace via rectal', $L_ayuda);
        $add('¿Para qué sirve la ecografía de tiroides?', 'Evalúa la glándula tiroides y ayuda a detectar nódulos, quistes o cambios de tamaño. La interpretación la realiza tu médico.', 'tiroides para que sirve nodulos cuello glandula detecta bocio', null);
        $add('¿La ecografía musculoesquelética sirve para tendones o desgarros?', 'Sí. Evalúa músculos, tendones, ligamentos y articulaciones; es útil en lesiones deportivas, desgarros o tendinitis.', 'musculoesqueletica tendon desgarro lesion deportiva tendinitis musculo articulacion sirve esguince', null);
        $add('¿Para qué sirve la ecografía testicular?', 'Evalúa los testículos y el escroto ante dolor, inflamación, bultos u otras molestias indicadas por tu médico.', 'testicular escroto para que sirve dolor bulto inflamacion varicocele', null);

        /* Citas: gestiones adicionales */
        $add('¿Recibiré un recordatorio de mi cita?', 'Tu cita y su estado están siempre visibles en “Mis Citas”, y verás notificaciones dentro del sistema cuando haya cambios.', 'recordatorio recordar aviso cita notificacion sms recuerdan avisaran', $L_citas);
        $add('¿Puedo cambiar la hora de una cita ya confirmada?', 'Para cambios en una cita confirmada, contacta a recepción; coordinarán la nueva hora según la disponibilidad.', 'cambiar hora cita confirmada modificar horario mover ajustar', $L_ayuda);
        $add('¿Qué hago si no veo horarios disponibles?', 'Si no aparecen cupos, prueba con otra fecha u otro ecografista, o contacta a recepción para más opciones.', 'no hay horarios disponibles cupos lleno no aparece fecha sin disponibilidad agotado', $L_ayuda);
        $add('¿Puedo agendar por teléfono o WhatsApp?', 'Puedes solicitar tu cita desde el sistema en “Solicitar nueva cita”. Para gestiones por otros medios, consulta el Centro de Ayuda.', 'telefono whatsapp llamar agendar por correo otro medio mensaje', $L_solicitar);

        /* Informes y trámites */
        $add('¿Hasta cuándo estarán disponibles mis informes?', 'Tus informes finalizados permanecen disponibles en “Mis Informes” para que los consultes cuando los necesites.', 'hasta cuando disponibles informes guardados historico antiguos vencen caducan tiempo', $L_informes);
        $add('¿Puedo pedir copia de un informe anterior?', 'Tus informes anteriores están en “Mis Informes”; puedes abrirlos e imprimirlos. Si necesitas algo adicional, contacta a recepción.', 'copia informe anterior antiguo duplicado pasado solicitar reimprimir', $L_informes);
        $add('¿El informe sirve para mi seguro o un trámite?', 'El informe oficial firmado puede usarse para tus trámites. Confirma con tu aseguradora o institución los requisitos específicos.', 'informe sirve seguro tramite valido oficial legal reembolso reposo laboral', $L_informes);

        /* Situaciones especiales */
        $add('¿Atienden a personas en silla de ruedas o con movilidad reducida?', 'Sí. Si requieres apoyo por movilidad reducida, avísanos al llegar o por el Centro de Ayuda para asistirte mejor.', 'silla ruedas discapacidad movilidad reducida apoyo accesible adulto mayor anciano', $L_ayuda);
        $add('Tengo síntomas de gripe o COVID, ¿puedo ir a mi cita?', 'Si tienes síntomas respiratorios o fiebre, comunícate con recepción para reprogramar y cuidar a todos. Tu salud es lo primero.', 'gripe covid sintomas fiebre enfermo tos resfriado puedo ir contagio malestar', $L_citas);
        $add('Me da ansiedad o claustrofobia, ¿cómo es el estudio?', 'La ecografía se realiza en una camilla, en un espacio abierto y sin encierros. Cuéntale al ecografista cómo te sientes; te acompañará durante todo el estudio.', 'ansiedad claustrofobia nervios miedo encierro abierto tranquilo panico estres', null);
        $add('¿Puedo amamantar a mi bebé en la clínica?', 'Sí. Si necesitas un espacio para amamantar, pídelo en recepción y te orientamos.', 'amamantar lactancia bebe dar pecho clinica espacio lactario', null);

        /* Preparación: más casos */
        $add('¿Debo suspender algún medicamento antes del estudio?', 'No suspendas ningún medicamento sin indicación de tu médico. Para la ecografía no suele ser necesario suspender tratamientos.', 'suspender medicamento dejar pastilla tratamiento antes anticoagulante pausar', null);
        $add('¿Los niños deben hacer ayuno?', 'Depende del estudio. Para estudios abdominales en niños, recepción te indicará un ayuno más corto y adaptado a su edad.', 'niños ayuno bebe pediatrico ayunar cuanto adaptado infantil', $L_ayuda);

        /* Portal y tecnología */
        $add('¿Hay una aplicación móvil?', 'Puedes usar el sistema desde el navegador de tu celular o computadora; está adaptado para pantallas pequeñas.', 'app aplicacion movil celular telefono descargar play store responsive instalar', null);
        $add('¿Cómo veo una cita pasada?', 'En “Mis Citas” usa el filtro “Historial” para ver tus citas anteriores y sus detalles.', 'cita pasada anterior historial ver pasadas antiguas previas vieja', $L_citas);
        $add('¿Quién puede ver mis informes?', 'Solo tú y el personal autorizado de la clínica pueden acceder a tus informes; tu información es confidencial.', 'quien ve informes acceso confidencial privacidad datos personal ver mis seguridad', null);

        /* Políticas y administración */
        $add('¿Cuál es la política de cancelación?', 'Puedes cancelar o reprogramar con anticipación desde “Mis Citas” o avisando a recepción, para liberar el cupo a otro paciente.', 'politica cancelacion cancelar reglas anticipacion penalizacion plazo', $L_citas);
        $add('¿Cobran si no asisto a la cita?', 'Para conocer cualquier condición por inasistencia, consulta con recepción. Lo ideal es avisar con tiempo si no podrás asistir.', 'cobran multa penalizacion inasistencia no asisto falto cargo', $L_ayuda);
        $add('¿Hacen descuentos o promociones?', 'Las promociones o descuentos vigentes los informa recepción. Consúltalos al momento de agendar.', 'descuento promocion oferta rebaja barato economico precio especial', $L_ayuda);
        $add('¿El precio incluye el informe?', 'El informe del estudio forma parte de tu atención. Cualquier detalle del costo lo confirma recepción al agendar.', 'precio incluye informe costo aparte adicional cobran extra', $L_ayuda);
        $add('¿Puedo pedir una constancia o reposo?', 'La constancia de asistencia o cualquier documento adicional puedes solicitarlo en recepción el día de tu estudio.', 'constancia justificativo reposo certificado asistencia documento laboral', $L_ayuda);

        /* Salud y bienestar antes del estudio */
        $add('¿Puedo hacerme el estudio si tengo la regla?', 'En la mayoría de los casos sí. Para estudios pélvicos, si tienes dudas por la menstruación, consúltalo con recepción o tu médico.', 'regla menstruacion periodo pelvica puedo durante sangrado dia', $L_ayuda);
        $add('Tengo presión alta o tomo medicación, ¿puedo ir?', 'Sí. Toma tu medicación habitual como siempre (con un poco de agua, incluso en ayuno) salvo indicación distinta de tu médico.', 'presion alta hipertension medicacion tomar puedo cronico tratamiento', null);
        $add('¿Necesito estar acompañado por algún motivo de salud?', 'No es obligatorio, pero si tienes una condición que requiera apoyo, puedes asistir con un acompañante y avisarlo en recepción.', 'acompañante salud apoyo necesito condicion mareo asistencia', null);
        $add('Me mareo con facilidad, ¿hay algún riesgo?', 'La ecografía es un estudio tranquilo en camilla. Si te sientes mal en cualquier momento, avisa al ecografista para asistirte.', 'mareo desmayo nausea malestar riesgo me siento mal vahido', null);

        /* Lenguaje coloquial / variantes */
        $add('¿Cuánto sale el eco?', 'El costo del estudio lo confirma recepción al agendar, ya que depende del tipo de ecografía.', 'cuanto sale eco vale cuesta precio plata real dolar barato', $L_ayuda);
        $add('¿Cómo saco una cita?', 'Entra a “Solicitar nueva cita”, elige el estudio, el ecografista, la fecha y la hora, y envía tu solicitud.', 'sacar cita pedir conseguir agarrar reservar como saco turno', $L_solicitar);
        $add('¿Dónde veo mis exámenes?', 'Tus informes están en la sección “Mis Informes”, donde puedes abrirlos e imprimirlos.', 'examenes resultados estudios ver donde mis informes consultar', $L_informes);
        $add('¿Tengo que estar en ayunas?', 'Solo para la ecografía abdominal o abdominal total (6 a 8 horas). La mayoría de los demás estudios no requieren ayuno.', 'ayunas ayuno en blanco sin comer estomago vacio tengo que', $L_prep);
        $add('¿Me van a poner una inyección o contraste?', 'No. La ecografía no usa inyecciones, agujas ni contraste; solo gel sobre la piel.', 'inyeccion contraste aguja pinchazo intravenoso medio inyectan ponen', null);

        /* Después del estudio / seguimiento */
        $add('¿Qué hago después de recibir mi informe?', 'Lleva tu informe a tu médico tratante, quien lo interpreta junto con tu historia y te indica los siguientes pasos.', 'despues informe que hago siguiente paso resultado llevar medico interpretar control', $L_informes);
        $add('¿Necesito una nueva cita para que me expliquen el resultado?', 'La interpretación del resultado la realiza tu médico tratante. Si necesitas un nuevo estudio o control, puedes agendarlo cuando lo indique.', 'explicar resultado nueva cita control seguimiento interpretacion consulta', $L_solicitar);
        $add('¿Cada cuánto debo repetir mi ecografía?', 'La frecuencia de control la define tu médico según tu caso. Cuando lo indique, puedes agendar el siguiente estudio.', 'cada cuanto repetir control frecuencia seguimiento periodicidad nuevamente', $L_solicitar);

        /* Misceláneos del sistema */
        $add('¿Puedo usar el sistema desde mi teléfono?', 'Sí. El sistema está adaptado para celulares y computadoras; ábrelo desde el navegador de tu dispositivo.', 'telefono celular movil usar sistema navegador computadora tablet acceder', null);
        $add('No puedo iniciar sesión', 'Verifica tu correo y contraseña. Si olvidaste la clave, usa la recuperación en la pantalla de inicio o contacta a recepción.', 'no puedo iniciar sesion entrar login acceder error contraseña clave bloqueado', $L_ayuda);
        $add('¿Cómo cambio mi correo o teléfono?', 'Actualiza tus datos de contacto desde “Mi Perfil”.', 'cambiar correo email telefono numero datos contacto actualizar editar perfil', $L_perfil);
        $add('¿El sistema está en otros idiomas?', 'Actualmente el sistema está en español. Para asistencia en otro idioma, consulta en recepción.', 'idioma ingles otro lenguaje traduccion language español', $L_ayuda);

        return $kb;
    }
}
