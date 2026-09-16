import React from 'react';
import { Image, StyleSheet, Text, View } from 'react-native';
import AppButton from '../../components/AppButton';
import HeaderBlock from '../../components/HeaderBlock';
import Screen from '../../components/Screen';
import { colors } from '../../theme/colors';
import traceLogo from '../../assets/images/trace-logo.jpg';

export default function WelcomeScreen({ navigation }) {
  return (
    <Screen style={styles.container}>
      <View style={styles.hero}>
        <Image source={traceLogo} style={styles.logo} resizeMode="contain" accessibilityLabel="TRACE logo" />
        <Text style={styles.tagline}>Track trips, protect children, and keep every parent informed.</Text>
      </View>
      <HeaderBlock
        eyebrow="Child Transport"
        title="A single app flow for parents, drivers, and children."
        subtitle="Manage registration, child transport, driver assignments, live status, notifications, and trip coordination."
      />
      <AppButton label="Login" onPress={() => navigation.navigate('Login')} />
      <AppButton label="Create an Account" variant="secondary" onPress={() => navigation.navigate('RegisterRole')} />
    </Screen>
  );
}

const styles = StyleSheet.create({
  container: {
    justifyContent: 'center',
  },
  hero: {
    backgroundColor: colors.ink,
    borderRadius: 30,
    paddingHorizontal: 24,
    paddingVertical: 22,
    marginBottom: 22,
    alignItems: 'center',
  },
  logo: {
    width: '100%',
    maxWidth: 260,
    height: 190,
    marginBottom: 10,
    borderRadius: 18,
  },
  tagline: {
    color: colors.heroText,
    fontSize: 16,
    lineHeight: 24,
    textAlign: 'center',
  },
});
