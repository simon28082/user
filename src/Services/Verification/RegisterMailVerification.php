<?php

/**
 * @author simon <crcms@crcms.cn>
 * @datetime 2018-04-07 20:25
 * @link http://crcms.cn/
 * @copyright Copyright &copy; 2018 Rights Reserved CRCMS
 */

namespace CrCms\User\Services\Verification;

use Carbon\Carbon;
use CrCms\App\Helpers\Hash\Contracts\HashVerify;
use CrCms\User\Attributes\UserAttribute;
use CrCms\User\Models\UserVerificationModel;
use CrCms\User\Repositories\UserVerificationRepository;
use CrCms\User\Services\Verification\Contracts\Verification;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;

/**
 * Class RegisterVerification
 * @package CrCms\User\Services\Verification
 */
class RegisterMailVerification implements Verification
{
    /**
     * @var UserVerificationRepository
     */
    protected $userVerificationRepository;

    /**
     * @var HashVerify
     */
    protected $hashVerify;

    /**
     * @var UserVerificationModel
     */
    protected $userVerification;

    /**
     * @var Request
     */
    protected $request;

    /**
     * Register constructor.
     * @param Request $request
     * @param HashVerify $hashVerify
     * @param UserVerificationRepository $userVerificationRepository
     */
    public function __construct(Request $request, HashVerify $hashVerify, UserVerificationRepository $userVerificationRepository)
    {
        $this->request = $request;
        $this->hashVerify = $hashVerify;
        $this->userVerificationRepository = $userVerificationRepository;
    }

    /**
     * @return bool
     * @throws ValidationException
     */
    public function validate(): bool
    {
        $this->validator($this->request->all())->validate();

        $this->setUserVerification(
            $this->userVerification($this->request->input('id'))
        );

        $this->checkHash();

        $this->checkTime();

        $this->checkVerified();

        return true;
    }

    /**
     * @param array $data
     * @return UserVerificationModel
     */
    public function update(array $data = []): UserVerificationModel
    {
        $data = array_merge(['status' => UserAttribute::VERIFY_STATUS_SUCCESS], $data);

        $userVerification = $this->userVerificationRepository->update($data, $this->userVerification->id);

        $this->setUserVerification($userVerification);

        return $userVerification;
    }

    /**
     * @param int $userId
     * @param int $type
     * @return UserVerificationModel
     */
    public function create(int $userId, int $type): UserVerificationModel
    {
        $userVerification = $this->userVerificationRepository->create([
            'user_id' => $userId,
            'type' => $type,
            'status' => UserAttribute::VERIFY_STATUS_NO,
        ]);

        $this->setUserVerification($userVerification);

        return $userVerification;
    }

    /**
     * @return UserVerificationModel
     */
    public function getUserVerification(): UserVerificationModel
    {
        return $this->userVerification;
    }

    /**
     * @return array
     */
    public function url(): array
    {
        $options = [
            'id' => $this->userVerification->id,
            'sign' => Str::random(10),
            'time' => $this->userVerification->created_at->getTimestamp(),
        ];

        $hash = $this->hashVerify->make($options);

        $options = array_merge($options, ['hash' => $hash]);

        return route('register_mail_verify.post', $options);
    }

    /**
     * @param int $id
     * @return UserVerificationModel
     */
    protected function userVerification(int $id): UserVerificationModel
    {
        return $this->userVerificationRepository->byIntIdOrFail($id);
    }

    /**
     * @param UserVerificationModel $userVerification
     * @return Register
     */
    protected function setUserVerification(UserVerificationModel $userVerification): self
    {
        $this->userVerification = $userVerification;
        return $this;
    }

    /**
     * @param array $data
     * @return Validator
     */
    protected function validator(array $data): Validator
    {
        return Validator::make($data, [
            'hash' => 'required|string',
            'id' => 'required|integer',
            'time' => 'required|integer',
            'sign' => 'required',
        ]);
    }

    /**
     * @return bool
     * @throws ValidationException
     */
    protected function checkVerified(): bool
    {
        if ($this->userVerification->status === UserAttribute::VERIFY_STATUS_SUCCESS) {
            $this->throwError([
                'id' => [trans('user::verify_mail.verified')],
            ]);
        }

        return true;
    }

    /**
     * @return bool
     * @throws ValidationException
     */
    protected function checkHash(): bool
    {
        if (!$this->hashVerify->check([
            'id' => $this->userVerification->id,
            'sign' => $this->request->input('sign'),
            'time' => $this->userVerification->created_at->getTimestamp(),
        ], $this->request->input('hash'))) {

            $this->throwError([
                'hash' => [trans('user::verify_mail.hash_error')],
            ]);
        }

        return true;
    }

    /**
     * @return bool
     * @throws ValidationException
     */
    protected function checkTime(): bool
    {
        if (
            Carbon::now()->diffInMinutes($this->userVerification->created_at) > config('user.register_mail_time_interval', 10)
        ) {
            $this->throwError([
                'id' => [trans('user::verify_mail.time_error')],
            ]);
        }

        return true;
    }

    /**
     * @param array $errors
     * @throws ValidationException
     * @return void
     */
    protected function throwError(array $errors): void
    {
        throw ValidationException::withMessages($errors)->status(423);
    }
}