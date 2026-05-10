import React, { useState } from 'react';
import { StyleSheet, Text, View } from 'react-native';
import AppButton from '../../components/AppButton';
import FormInput from '../../components/FormInput';
import HeaderBlock from '../../components/HeaderBlock';
import Screen from '../../components/Screen';
import SectionCard from '../../components/SectionCard';
import { useAppContext } from '../../context/AppContext';
import { colors } from '../../theme/colors';

export default function LoginScreen({ navigation }) {
  const { login, loading, error } = useAppContext();
  const [form, setForm] = useState({ email: '', password: '' });

  const updateField = (key, value) => setForm((current) => ({ ...current, [key]: value }));

  const handleLogin = async () => {
    try {
      await login({
        email: form.email.trim(),
        password: form.password,
      });
      navigation.replace('MainApp');
    } catch {
      // The Redux slice stores the backend error for display.
    }
  };

  return (
    <Screen>
      <HeaderBlock
        eyebrow="Secure Login"
        title="Sign in to TRACE."
        subtitle="Use the account created in the admin panel or through mobile registration."
      />

      <SectionCard title="Account credentials">
        <FormInput label="Email" value={form.email} onChangeText={(value) => updateField('email', value)} placeholder="name@example.com" keyboardType="email-address" />
        <FormInput label="Password" value={form.password} onChangeText={(value) => updateField('password', value)} placeholder="Password" secureTextEntry />
        {error ? <Text style={styles.error}>{error}</Text> : null}
        <AppButton label={loading ? 'Signing in...' : 'Sign In'} onPress={handleLogin} />
      </SectionCard>

      <View style={styles.footer}>
        <Text style={styles.footerText}>Need a new account?</Text>
        <AppButton label="Go to Registration" variant="ghost" onPress={() => navigation.navigate('RegisterRole')} />
      </View>
    </Screen>
  );
}

const styles = StyleSheet.create({
  footer: {
    marginTop: 6,
  },
  footerText: {
    fontSize: 14,
    marginBottom: 10,
    color: '#5c677d',
  },
  error: {
    color: colors.danger,
    marginBottom: 12,
    fontWeight: '700',
  },
});
