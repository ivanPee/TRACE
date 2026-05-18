import React, { useEffect } from 'react';
import { Image, StyleSheet, Text, View } from 'react-native';
import { colors } from '../../theme/colors';

const traceLogo = require('../../assets/trace-logo.png');

export default function SplashScreen({ navigation }) {
  useEffect(() => {
    const timer = setTimeout(() => {
      navigation.replace('Welcome');
    }, 1200);

    return () => clearTimeout(timer);
  }, [navigation]);

  return (
    <View style={styles.container}>
      <View style={styles.glowBlue} />
      <View style={styles.glowGreen} />
      <View style={styles.glowYellow} />
      <Image source={traceLogo} style={styles.logo} resizeMode="contain" />
      <Text style={styles.title}>TRACE</Text>
      <Text style={styles.subtitle}>Student Transport Management System</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: colors.ink,
    padding: 24,
  },
  glowBlue: {
    position: 'absolute',
    width: 320,
    height: 320,
    borderRadius: 160,
    backgroundColor: 'rgba(7, 142, 255, 0.18)',
    top: 120,
    left: -40,
  },
  glowGreen: {
    position: 'absolute',
    width: 260,
    height: 260,
    borderRadius: 130,
    backgroundColor: 'rgba(34, 214, 30, 0.18)',
    top: 100,
    right: -10,
  },
  glowYellow: {
    position: 'absolute',
    width: 220,
    height: 220,
    borderRadius: 110,
    backgroundColor: 'rgba(255, 198, 27, 0.16)',
    bottom: 140,
    left: 30,
  },
  logo: {
    width: 240,
    height: 240,
    marginBottom: 10,
  },
  title: {
    fontSize: 38,
    fontWeight: '900',
    color: colors.white,
    letterSpacing: 3,
  },
  subtitle: {
    marginTop: 14,
    fontSize: 16,
    color: '#DDE5EE',
    textAlign: 'center',
  },
});
