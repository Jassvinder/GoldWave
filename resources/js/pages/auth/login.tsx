import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { store } from '@/routes/login';
import { request } from '@/routes/password';
import { store as storeLogin } from '@/routes/store-login';
import PasskeyVerify from '@/components/passkey-verify';

type Props = {
    status?: string;
    canResetPassword: boolean;
};

/** T-117 (19-09-2026) — two fully separate login flows on this one page, per the user's own explicit instruction: Super Admin keeps the original Fortify email+password form untouched; Admin/Store Login is new (Store ID + Store password), never merged with the other. Member login stays entirely separate, at `/member/login`. */
export default function Login({ status, canResetPassword }: Props) {
    return (
        <>
            <Head title="Log in" />

            <PasskeyVerify />

            <Tabs defaultValue="super-admin">
                <TabsList className="w-full">
                    <TabsTrigger value="super-admin">
                        Super Admin / Admin Login
                    </TabsTrigger>
                    <TabsTrigger value="admin-store">
                        Store Admin Login
                    </TabsTrigger>
                </TabsList>

                <TabsContent value="super-admin">
                    <Form
                        {...store.form()}
                        resetOnSuccess={['password']}
                        className="flex flex-col gap-6"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-6">
                                    <div className="grid gap-2">
                                        <Label htmlFor="email">
                                            Email address
                                        </Label>
                                        <Input
                                            id="email"
                                            type="email"
                                            name="email"
                                            required
                                            autoFocus
                                            tabIndex={1}
                                            autoComplete="email"
                                            placeholder="email@example.com"
                                        />
                                        <InputError message={errors.email} />
                                    </div>

                                    <div className="grid gap-2">
                                        <div className="flex items-center">
                                            <Label htmlFor="password">
                                                Password
                                            </Label>
                                            {canResetPassword && (
                                                <TextLink
                                                    href={request()}
                                                    className="ml-auto text-sm"
                                                    tabIndex={5}
                                                >
                                                    Forgot your password?
                                                </TextLink>
                                            )}
                                        </div>
                                        <PasswordInput
                                            id="password"
                                            name="password"
                                            required
                                            tabIndex={2}
                                            autoComplete="current-password"
                                            placeholder="Password"
                                        />
                                        <InputError message={errors.password} />
                                    </div>

                                    <div className="flex items-center space-x-3">
                                        <Checkbox
                                            id="remember"
                                            name="remember"
                                            tabIndex={3}
                                        />
                                        <Label htmlFor="remember">
                                            Remember me
                                        </Label>
                                    </div>

                                    <Button
                                        type="submit"
                                        className="mt-4 w-full"
                                        tabIndex={4}
                                        disabled={processing}
                                        data-test="login-button"
                                    >
                                        {processing && <Spinner />}
                                        Log in
                                    </Button>
                                </div>
                            </>
                        )}
                    </Form>
                </TabsContent>

                <TabsContent value="admin-store">
                    <Form
                        {...storeLogin.form()}
                        resetOnSuccess={['password']}
                        className="flex flex-col gap-6"
                    >
                        {({ processing, errors }) => (
                            <div className="grid gap-6">
                                <div className="grid gap-2">
                                    <Label htmlFor="store_code">Store ID</Label>
                                    <Input
                                        id="store_code"
                                        type="text"
                                        name="store_code"
                                        required
                                        autoFocus
                                        tabIndex={1}
                                        placeholder="GWLST0001"
                                    />
                                    <InputError message={errors.store_code} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="store_password">
                                        Password
                                    </Label>
                                    <PasswordInput
                                        id="store_password"
                                        name="password"
                                        required
                                        tabIndex={2}
                                        autoComplete="current-password"
                                        placeholder="Password"
                                    />
                                    <InputError message={errors.password} />
                                </div>

                                <Button
                                    type="submit"
                                    className="mt-4 w-full"
                                    tabIndex={3}
                                    disabled={processing}
                                >
                                    {processing && <Spinner />}
                                    Log in
                                </Button>
                            </div>
                        )}
                    </Form>
                </TabsContent>
            </Tabs>

            {status && (
                <div className="mb-4 text-center text-sm font-medium text-green-600">
                    {status}
                </div>
            )}
        </>
    );
}

Login.layout = {
    title: 'Log in to your account',
    description:
        'Super Admin or Admin, or Store Admin — choose the matching tab below',
};
