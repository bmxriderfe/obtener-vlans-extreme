#!/usr/local/bin/php -q
<?php

/**
 * script construido por Felipe Ramirez Farias para cargar todas las vlan de los accesos
 * en la base de datos
 * 
 * version: 1.0
 * autor: Felipe Ramirez Farias <feliperafarias@gmail.com>
 * colaboracion: Eduardo Cabrera Flores <lalo@eof.cl>
 * fecha: 01-08-2025
 *
 **/

if ($argc < 3) {
    echo "Usage: php obtener_vlans.php <host> <snmp_community>\n";
    exit(1);
}

$host = $argv[1];
$community = $argv[2];

// Validación básica
if (empty($host) || empty($community)) {
    echo "Ambos parametros requeridos.\n";
    exit(1);
}

snmp_set_valueretrieval(SNMP_VALUE_PLAIN);
$vlans = [];
$vlansTag = [];
$vlansUnTag = [];
$ptasVlans = [];
$puertas = [];

//EXTREME-VLAN-MIB::extremeVlanIfIndex
//$extremeVlanIfIndex = snmp2_walk($equipo, $community, '.1.3.6.1.4.1.1916.1.2.7.1.1.1');

// Primero se obtienen los nombres de las vlans
//EXTREME-VLAN-MIB::extremeVlanIfVlanId
$extremeVlanIfVlanId = snmp2_real_walk($equipo, $community, '.1.3.6.1.4.1.1916.1.2.1.2.1.10');
if (is_array($extremeVlanIfVlanId)) {
    foreach ($extremeVlanIfVlanId as $oid => $vlan) {
        preg_match("/\.(\d+)$/", $oid, $m);
        $vlans[$m[1]] = $vlan;
    }
}

snmp_set_valueretrieval(SNMP_VALUE_LIBRARY);

// Luego obtenemos todas las vlans Tagged
//EXTREME-VLAN-MIB::extremeVlanOpaqueTaggedPorts
$extremeVlanOpaqueTaggedPorts = snmp2_real_walk($equipo, $community, '.1.3.6.1.4.1.1916.1.2.6.1.1.1');
if (is_array($extremeVlanOpaqueTaggedPorts)) {
    foreach ($extremeVlanOpaqueTaggedPorts as $oid => $hexPuertas) {
        $hexPuertas = explode(":", $hexPuertas);
        $hexPuertas = str_replace(["\n", " "], "", $hexPuertas[1]);
        preg_match("/\.(\d+)\.1$/", $oid, $m);
        $vlansTag[$m[1]] = _str2bin($hexPuertas);
    }
}

// Luego todas las Untagged (Nativas)  - generalmente 1 por puerta
//EXTREME-VLAN-MIB::extremeVlanOpaqueUntaggedPorts
$extremeVlanOpaqueUntaggedPorts = snmp2_real_walk($equipo, $community, '.1.3.6.1.4.1.1916.1.2.6.1.1.2');
if (is_array($extremeVlanOpaqueUntaggedPorts)) {
    foreach ($extremeVlanOpaqueUntaggedPorts as $oid => $hexPuertas) {
        $hexPuertas = explode(":", $hexPuertas);
        $hexPuertas = str_replace(["\n", " "], "", $hexPuertas[1]);
        preg_match("/\.(\d+)\.1$/", $oid, $m);
        $vlansUnTag[$m[1]] = _str2bin($hexPuertas);
    }
}

// Luego combinamos los arreglos obtenidos para generar arreglos asociativos 
// que representan el nombre de la puerta con todas las VLANS configuradas en esa puerta
// la VLAN nativa configurada en esa puerta se marca con una "u" de "untagged"
foreach ($vlansTag as $vlanIfIndex => $puertasBin) {
    for ($i = 0; $i < strlen($puertasBin); $i++) {
        $npta = $i + 1;

        if ($puertasBin[$i] == 1) {
            $puertas["1/{$npta}"][] = $vlans[$vlanIfIndex];
        }
        if ($vlansUnTag[$vlanIfIndex][$i] == 1) {
            $puertas["1/{$npta}"][] = "$vlans[$vlanIfIndex]u";
        }
    }
}

print_r($puertas);


