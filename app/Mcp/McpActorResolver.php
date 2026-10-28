<?php

declare(strict_types=1);

namespace App\Mcp;

use ArtisanBuild\BuiltForCloud\Console\ActingPrincipalResolver;
use ArtisanBuild\BuiltForCloud\Console\DelegatedActor;
use ArtisanBuild\BuiltForCloud\Credential;
use ArtisanBuild\BuiltForCloud\Exceptions\ManagedAuthRefused;
use ArtisanBuild\BuiltForCloud\ManagedAccountAccess;
use ArtisanBuild\BuiltForCloud\ManagedAuthConnection;
use ArtisanBuild\BuiltForCloud\OffboardedSubject;
use ArtisanBuild\BuiltForCloud\OperatorAbility;
use ArtisanBuild\BuiltForCloud\RolePolicy;
use ArtisanBuild\BuiltForCloud\Subject;
use ArtisanBuild\BuiltForCloud\SubjectType;
use ArtisanBuild\BuiltForCloud\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;

final readonly class McpActorResolver
{
    public function __construct(
        private Request $request,
        private ActingPrincipalResolver $principals,
        private ManagedAccountAccess $managedAccess,
    ) {}

    public function resolve(OperatorAbility $ability): User
    {
        try {
            $acting = $this->principals->require($this->request);
        } catch (\Throwable) {
            throw new AuthenticationException;
        }

        if ($acting->delegatedActor instanceof DelegatedActor) {
            $user = $this->delegatedUser($acting->delegatedActor);
        } elseif ($acting->principal instanceof Credential) {
            if (! $acting->principal->hasAbility($ability->value)) {
                throw new AuthorizationException;
            }

            $user = $this->credentialUser($acting->principal);
        } else {
            $user = $acting->principal instanceof User ? $acting->principal : null;
        }

        if (! $user instanceof User || ! $this->admitted($user)) {
            throw new AuthenticationException;
        }

        return $user;
    }

    private function delegatedUser(DelegatedActor $actor): ?User
    {
        try {
            $connection = ManagedAuthConnection::current();
        } catch (ManagedAuthRefused) {
            return null;
        }

        if (! hash_equals($connection->issuer, $actor->issuer)) {
            return null;
        }

        return User::query()
            ->where('scalpels_issuer', $connection->issuer)
            ->where('scalpels_connection_id', $connection->connectionId)
            ->where('scalpels_id', $actor->subject)
            ->first();
    }

    private function credentialUser(Credential $credential): ?User
    {
        $userId = filter_var(
            $credential->user_id,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]],
        );

        return is_int($userId) ? User::query()->find($userId) : null;
    }

    private function admitted(User $user): bool
    {
        $userId = (string) $user->getAuthIdentifier();

        return $user->status === 'active'
            && RolePolicy::canUseProduct($user->role)
            && ! OffboardedSubject::subjectIsOffboarded(
                new Subject(SubjectType::UserPrincipal, 'draw-user:'.$userId),
            )
            && ! OffboardedSubject::userIsOffboarded($userId)
            && $this->managedAccess->allows($user);
    }
}
