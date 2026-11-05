<?php

namespace App\DTOs;

class UserDTO
{
    public function __construct(
        public readonly string $name,
        public readonly string $paterno,
        public readonly ?string $materno,

        public readonly string $ci,
        public readonly ?string $ci_extension,
        public readonly string $sexo,

        public readonly string $email,
        public readonly ?string $celular,

        public readonly string $cargo,
        public readonly string $area,
        public readonly ?string $oficina_regional,

        public readonly int $id_rol,
        public readonly ?bool $activo = null,
        public readonly ?string $password = null,
        public readonly ?string $face_photo = null,
        public readonly mixed $face_descriptor = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            paterno: $data['paterno'],
            materno: $data['materno'] ?? null,

            ci: $data['ci'],
            ci_extension: $data['ci_extension'] ?? null,
            sexo: $data['sexo'],

            email: $data['email'],
            celular: $data['celular'] ?? null,

            cargo: $data['cargo'],
            area: $data['area'],
            oficina_regional: $data['oficina_regional'] ?? null,

            id_rol: (int) $data['id_rol'],
            activo: isset($data['activo']) ? (bool) $data['activo'] : null,
            password: $data['password'] ?? null,
            face_photo: $data['face_photo'] ?? null,
            face_descriptor: $data['face_descriptor'] ?? null,
        );
    }

    public function toArray(): array
    {
        $data = [
            'name' => $this->name,
            'paterno' => $this->paterno,
            'materno' => $this->materno,

            'ci' => $this->ci,
            'ci_extension' => $this->ci_extension,
            'sexo' => $this->sexo,

            'email' => $this->email,
            'celular' => $this->celular,

            'cargo' => $this->cargo,
            'area' => $this->area,
            'oficina_regional' => $this->oficina_regional,

            'id_rol' => $this->id_rol,
        ];

        // Solo se envían si vienen en la petición, para no borrar
        // el registro facial ni cambiar el estado al editar.
        $opcionales = array_filter([
            'activo' => $this->activo,
            'face_photo' => $this->face_photo,
            'face_descriptor' => $this->face_descriptor,
        ], fn ($valor) => $valor !== null);

        return $data + $opcionales;
    }
}
