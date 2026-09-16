import React, { useEffect } from 'react';
import { Image, StyleSheet, Text, View } from 'react-native';
import { colors } from '../../theme/colors';
import traceLogo from '../../assets/images/trace-logo.jpg';

export default function SplashScreen({ navigation }) {
  useEffect(() => {
    const timer = setTimeout(() => {
      navigation.replace('Welcome');
    }, 1200);

    return () => clearTimeout(timer);
  }, [navigation]);

  return (
    <View style={styles.container}>
      <View style={styles.badge}>
        <Image source={traceLogo} style={styles.logo} resizeMode="contain" accessibilityLabel="TRACE logo" />
      </View>
      <Text style={styles.subtitle}>Real-time child transport management</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: colors.sand,
    padding: 24,
  },
  badge: {
    width: '100%',
    maxWidth: 280,
    backgroundColor: colors.white,
    paddingHorizontal: 18,
    paddingVertical: 16,
    borderRadius: 22,
  },
  logo: {
    width: '100%',
    height: 220,
  },
  subtitle: {
    marginTop: 18,
    fontSize: 16,
    color: colors.deep,
    textAlign: 'center',
  },
});
