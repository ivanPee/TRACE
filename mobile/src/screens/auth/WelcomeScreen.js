import React from 'react';
import { Image, StyleSheet, Text, View } from 'react-native';
import AppButton from '../../components/AppButton';
import HeaderBlock from '../../components/HeaderBlock';
import Screen from '../../components/Screen';
import { colors } from '../../theme/colors';

const traceLogo = require('../../assets/trace-logo.png');

export default function WelcomeScreen({ navigation }) {
  return (
    <Screen style={styles.container}>
      <View style={styles.hero}>
        <Image source={traceLogo} style={styles.logo} resizeMode="contain" />
        <Text style={styles.brand}>TRACE</Text>
        <Text style={styles.tagline}>Track rides, protect students, and keep every parent informed.</Text>
      </View>
      <HeaderBlock
        eyebrow="Student Transport"
        title="A single app flow for parents, drivers, and students."
        subtitle="Manage registration, student transport, driver assignments, live status, notifications, and trip coordination."
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
    padding: 24,
    marginBottom: 22,
  },
  logo: {
    width: 120,
    height: 120,
    marginBottom: 12,
    alignSelf: 'center',
  },
  brand: {
    color: colors.white,
    fontSize: 42,
    fontWeight: '900',
    marginBottom: 8,
    textAlign: 'center',
  },
  tagline: {
    color: '#DDE5EE',
    fontSize: 16,
    lineHeight: 24,
    textAlign: 'center',
  },
});
