<?php

declare(strict_types=1);

namespace ArioLabs\Talos\Enums;

// https://www.talos.dev/v1.10/reference/configuration/v1alpha1/config/#Config.cluster.network.cni
enum Cni: string
{
    case Flannel = 'flannel';
    case Custom = 'custom';
    case None = 'none';
}
